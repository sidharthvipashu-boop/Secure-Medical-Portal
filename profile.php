<?php
/**
 * =============================================================================
 *  crypto.php  --  The security engine of the whole project
 * =============================================================================
 *
 *  This single file contains the code for TWO of the three project ideas:
 *
 *    - Project Idea 1 (SEC-PRJ-12A_25): Secure Storage in Edge Cloud
 *          => encrypt_data() and decrypt_data()   [AES-256]
 *
 *    - Project Idea 2 (SEC-PRJ-7E_25):  Fake Data Prevention w/ Cryptotools
 *          => make_hash() and verify_integrity()  [SHA-256]
 *
 *  (Project Idea 3, federated storage, lives in functions.php but REUSES the
 *   encrypt_data() function from this file.)
 *
 *  Keeping all cryptography in one place means the security logic is easy to
 *  find, audit, and explain in a viva.
 * =============================================================================
 */

require_once __DIR__ . '/../config/config.php';

/* =============================================================================
 *  PROJECT IDEA 1  --  SEC-PRJ-12A_25  --  SECURE STORAGE IN EDGE CLOUD
 * =============================================================================
 *
 *  THE PROBLEM:
 *      A hospital stores highly sensitive data (diagnoses, prescriptions...).
 *      If someone steals the database file, plain-text data is exposed instantly.
 *
 *  THE SOLUTION (what we implement):
 *      Encrypt every sensitive value with AES-256 BEFORE it touches the database.
 *      The database therefore only ever holds unreadable ciphertext. Data is
 *      decrypted in memory ONLY when an authorised user views it, then discarded.
 *      This simulates an "edge cloud" node that keeps data confidential at rest.
 *
 *  DATA FLOW:
 *
 *        Plain text  --[ encrypt_data() ]-->  Ciphertext  -->  MySQL (at rest)
 *                                                                  |
 *        Plain text  <--[ decrypt_data() ]--  Ciphertext  <-------+
 *
 *  HOW AES-256-CBC WORKS HERE:
 *      - Key    : 256-bit secret from config.php (ENCRYPTION_KEY).
 *      - IV     : a fresh random 16-byte Initialisation Vector per encryption,
 *                 so encrypting the same text twice gives different output.
 *      - Output : we prepend the IV to the ciphertext and base64-encode the whole
 *                 thing, because we need that same IV later to decrypt. The IV is
 *                 NOT secret -- only the key must stay secret.
 * ---------------------------------------------------------------------------*/

/**
 * Encrypt a piece of text with AES-256-CBC.
 *
 * @param  string $plaintext  The sensitive value to protect (e.g. a diagnosis).
 * @return string             Base64 string of  (IV + ciphertext), safe to store.
 */
function encrypt_data($plaintext)
{
    // Empty input stays empty -- nothing sensitive to hide.
    if ($plaintext === null || $plaintext === '') {
        return '';
    }

    // 1) Work out how long the IV must be for this cipher (16 bytes for CBC).
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);

    // 2) Generate a cryptographically-secure RANDOM IV.
    //    A random IV is what makes CBC safe: identical plaintext -> different ciphertext.
    $iv = openssl_random_pseudo_bytes($iv_length);

    // 3) Perform the actual AES-256 encryption.
    //    OPENSSL_RAW_DATA => return raw bytes (we base64 them ourselves below).
    $ciphertext = openssl_encrypt(
        $plaintext,          // data to encrypt
        ENCRYPTION_CIPHER,   // 'aes-256-cbc'
        ENCRYPTION_KEY,      // 256-bit secret key
        OPENSSL_RAW_DATA,    // return raw binary
        $iv                  // the random IV we generated
    );

    // 4) We must keep the IV so we can decrypt later, so we glue it in front of
    //    the ciphertext, then base64-encode the combined bytes so the result is
    //    plain text that stores cleanly in a normal MySQL column.
    return base64_encode($iv . $ciphertext);
}

/**
 * Decrypt a value that was produced by encrypt_data().
 *
 * @param  string $stored  The base64 (IV + ciphertext) string read from the DB.
 * @return string          The original plain text, or '' if it cannot be decoded.
 */
function decrypt_data($stored)
{
    if ($stored === null || $stored === '') {
        return '';
    }

    // 1) Undo the base64 to get back the raw bytes (IV + ciphertext).
    $raw = base64_decode($stored);
    if ($raw === false) {
        return ''; // Not valid base64 -> cannot decrypt.
    }

    // 2) Split the raw bytes: the first 16 bytes are the IV, the rest is ciphertext.
    $iv_length  = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv         = substr($raw, 0, $iv_length);
    $ciphertext = substr($raw, $iv_length);

    // 3) Reverse the AES-256 encryption using the SAME key and the extracted IV.
    $plaintext = openssl_decrypt(
        $ciphertext,
        ENCRYPTION_CIPHER,
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );

    // If the key/IV are wrong or data was corrupted, openssl_decrypt returns false.
    return $plaintext === false ? '' : $plaintext;
}


/* =============================================================================
 *  PROJECT IDEA 2  --  SEC-PRJ-7E_25  --  FAKE DATA PREVENTION (CRYPTOTOOLS)
 * =============================================================================
 *
 *  THE PROBLEM:
 *      Even encrypted rows could be tampered with (swapped, edited at the DB
 *      level, or replaced with "fake" data). How do we PROVE a medical record is
 *      exactly what the doctor originally entered, and has not been altered?
 *
 *  THE SOLUTION (what we implement):
 *      A cryptographic HASH acts like a digital fingerprint of the data.
 *        - When a record is created/updated, we compute a SHA-256 hash of its
 *          important fields and store that hash next to the record.
 *        - Every time the record is viewed, we recompute the hash from the CURRENT
 *          data and compare it to the stored hash.
 *        - Equal hashes  => data is intact and authentic.
 *        - Different     => data was tampered with => "Integrity Violation" +
 *                           the event is written to the security log.
 *
 *  WHY THIS WORKS:
 *      SHA-256 is a one-way function: changing even a single character produces a
 *      completely different hash, and it is infeasible to craft different data
 *      that yields the same hash. So a matching hash is strong evidence the data
 *      is genuine ("not fake"). This is exactly what "fake data prevention with
 *      conventional cryptotools" means.
 * ---------------------------------------------------------------------------*/

/**
 * Build the canonical "fingerprint string" for a medical record, then hash it.
 *
 * IMPORTANT: We ALWAYS hash the PLAIN-TEXT values (not the ciphertext). Ciphertext
 * changes every time because of the random IV, so hashing ciphertext would give a
 * false "tamper" alert. Hashing the plain text gives a stable, meaningful hash.
 *
 * @param  array  $fields  Ordered plain-text fields, e.g.
 *                         [diagnosis, treatment, prescription, notes].
 * @return string          A 64-character hex SHA-256 hash.
 */
function make_hash(array $fields)
{
    // Join fields with a separator that will not appear inside the data itself,
    // so "ab|c" and "a|bc" can never collide into the same string.
    $canonical = implode('||', $fields);

    // hash() with 'sha256' returns a 64-char hexadecimal fingerprint.
    return hash('sha256', $canonical);
}

/**
 * Verify that current data still matches a previously stored hash.
 *
 * @param  array  $fields       The current plain-text fields (same order as when created).
 * @param  string $stored_hash  The SHA-256 hash saved when the record was created.
 * @return bool                 true = intact/authentic, false = tampered/fake.
 */
function verify_integrity(array $fields, $stored_hash)
{
    // Recompute the fingerprint from the data as it stands right now.
    $current_hash = make_hash($fields);

    // hash_equals() compares the two hashes in constant time. Using it (instead of
    // '==') avoids timing side-channels -- a small but proper security detail.
    return hash_equals($stored_hash, $current_hash);
}

/**
 * Hash the raw bytes of an uploaded file (used to detect tampered reports).
 *
 * @param  string $file_contents  The full binary contents of the report file.
 * @return string                 SHA-256 hex fingerprint of the file.
 */
function make_file_hash($file_contents)
{
    return hash('sha256', $file_contents);
}
