<?php
declare(strict_types=1);

namespace App\Helper;

final class ValidationHelper
{
    /**
     * Sanitize color
     *
     * @param string $string
     * @return string
     */
    public function sanitizeColor(string $string): string {
        $string = trim($string);
        $string = strtolower($string);
        $string = preg_replace('/[^a-f0-9\#]/', '', $string);
        return $string;
    }

    /**
     * Sanitize string
     *
     * @param string $string
     * @return string
     */
    public function sanitizeString(string $string): string {
        $string = trim($string);
        $string = stripslashes($string);
        $string = strip_tags($string);
        return $string;
    }

    /**
     * Sanitize name
     * @param string $string
     * @return string
     */
    public function sanitizeName(string $string): string {
        $string = $this->sanitizeString($string);
        $string = preg_replace('/[^a-zA-Zà-üÀ-Ü0-9\'\-\s]/', '', $string);
        return $string;
    }

    /**
     * Sanitize username
     * @param string $string
     * @return string
     */
    public function sanitizeUsername(string $string): string {
        $string = $this->sanitizeString($string);
        $string = strtolower($string);
        $string = filter_var($string, FILTER_SANITIZE_EMAIL);
        return $string;
    }

    /**
     * Sanitize email
     * @param string $string
     * @return string
     */
    public function sanitizeEmail(string $string): string {
        $string = $this->sanitizeString($string);
        $string = strtolower($string);
        $string = filter_var($string, FILTER_SANITIZE_EMAIL);
        return $string;
    }



    /**
     * Validate color
     * @param string $string
     * @param bool $empty
     * @return bool
     */
    public function validateColor(string $string, $empty = false): bool {
        if (empty($string)) {
            return ($empty ? true : false);
        }

        if (!preg_match("/^#?[a-f0-9]{6}$/i",$string)) {
            return false;
        }

        if (mb_strlen($string) > 7) {
            return false;
        }

        return true;
    }

    /**
     * Validate date
     * @param string $value
     * @param bool $empty
     * @return bool
     */
    public function validateDate($value, bool $empty = false): bool {
        if (empty($value)) {
            return $empty;
        }

        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if ($value === false) {
            return false;
        }

        if (is_string($value)) {
            return strtotime($value) !== false;
        }

        return false;
    }

    /**
     * Validate email
     *
     * @param string $string
     * @param bool $empty
     * @return bool
     */
    public function validateEmail(string $string, $empty = false): bool {
        if (empty($string)) {
            return ($empty ? true : false);
        }

        if (!filter_var($string, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (mb_strlen($string) < 5 || mb_strlen($string) > 180) {
            return false;
        }



        return true;
    }

    /**
     * Validate max length
     *
     * @param string $string
     * @param int $maxLength
     * @return bool
     */
    public function validateMaxLength(string $string, int $maxLength, $empty = false): bool {
        if (empty($string)) {
            return ($empty ? true : false);
        }

        return mb_strlen($string) > $maxLength ? false : true;
    }

    /**
     * Validate min length
     *
     * @param string $string
     * @param int $minLength
     * @return bool
     */
    public function validateMinLength(string $string, int $minLength, $empty = false): bool {
        if (empty($string)) {
            return ($empty ? true : false);
        }

        return mb_strlen($string) < $minLength ? false : true;
    }

    /**
     * Validate name
     *
     * @param string $string
     * @return bool
     */
    public function validateName(string $string, $empty = false): bool {
        if (empty($string)) {
            return ($empty ? true : false);
        }

        if (!preg_match("/^[a-zA-Zà-üÀ-Ü0-9_\'\-\s\.]*$/",$string)) {
            return false;
        }

        if (mb_strlen($string) < 5 || mb_strlen($string) > 180) {
            return false;
        }

        return true;
    }

    /**
     * Validate number
     *
     * @param string $string
     * @return bool
     */
    public function validateNumber(string $string, $empty = false): bool {
        if (empty($string)) {
            return ($empty ? true : false);
        }

        if (!preg_match("/^[a-zA-Zà-üÀ-Ü0-9_\'\-\s\.]*$/",$string)) {
            return false;
        }

        if (mb_strlen($string) > 50) {
            return false;
        }

        return true;
    }

    /**
     * Validate username
     * @param string $string
     * @param int $minLength
     * @param bool $empty
     * @return bool
     */
    public function validateUsername(string $string, int $minLength = 6, $empty = false): bool {
        if (empty($string)) {
            return ($empty ? true : false);
        }

        if (mb_strlen($string) < $minLength || mb_strlen($string) > 180) {
            return false;
        }

        return true;
    }

    /**
     * Validate password strength
     * @param string $string
     * @param int $minLength
     * @param bool $empty
     * @return bool
     */
    public function validatePassword(string $string, int $minLength = 12, bool $empty = false): bool {
        if (empty($string)) {
            return ($empty ? true : false);
        }

        $length       = mb_strlen($string) < $minLength ? false : true;
        $uppercase    = preg_match('/[A-Z]/', $string);
        $lowercase    = preg_match('/[a-z]/', $string);
        $number       = preg_match('/[0-9]/', $string);
        $specialChars = preg_match('/[^\w]/', $string);
        if (!$uppercase || !$lowercase || !$number || !$specialChars || !$length) {
            return false;
        }

        return true;
    }

    /**
     * Validate array
     * @param int $value
     * @param array $haystack
     * @param bool $empty
     * @return bool
     */
    public function validateRole(int $value, array $haystack = array(1,2,3), bool $empty = false): bool {
        if (empty($value)) {
            return ($empty ? true : false);
        }

        if (!in_array($value, $haystack)) {
            return false;
        }

        return true;
    }






    // ...

}
