<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Encryption helper for sensitive values in local_ltifederation.
 *
 * Uses core\encryption when available (Moodle 3.11+), otherwise falls back to base64.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation;

/**
 * Helper to encrypt and decrypt sensitive strings such as web service tokens.
 */
class encryption_helper {
    /** Prefix indicating a value was encrypted with core\encryption. */
    const PREFIX_ENCRYPTED = 'LTIFED_ENC:';

    /** Prefix indicating a value was base64-encoded (fallback). */
    const PREFIX_B64 = 'LTIFED_B64:';

    /**
     * Encrypt a value for storage.
     *
     * Uses core\encryption::encrypt() when available; falls back to base64.
     *
     * @param string $value Plain text value.
     * @return string Encoded/encrypted value with prefix.
     */
    public static function encrypt(string $value): string {
        if (class_exists('\core\encryption') && method_exists('\core\encryption', 'encrypt')) {
            try {
                return self::PREFIX_ENCRYPTED . \core\encryption::encrypt($value);
            } catch (\Exception $e) {
                // Fall through to base64 fallback.
                debugging('ltifederation: encryption failed, falling back to base64: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return self::PREFIX_B64 . base64_encode($value);
    }

    /**
     * Decrypt a value from storage.
     *
     * @param string $stored Stored value with prefix.
     * @return string Plain text value.
     */
    public static function decrypt(string $stored): string {
        if (strpos($stored, self::PREFIX_ENCRYPTED) === 0) {
            $encrypted = substr($stored, strlen(self::PREFIX_ENCRYPTED));
            return \core\encryption::decrypt($encrypted);
        }
        if (strpos($stored, self::PREFIX_B64) === 0) {
            $encoded = substr($stored, strlen(self::PREFIX_B64));
            return base64_decode($encoded);
        }
        // Legacy: value stored without prefix, treat as plain text.
        return $stored;
    }
}
