<?php
/**
 * PHP Password Library
 *
 * @package PHPassLib\Hashes
 * @author Ryan Chouinard <rchouinard@gmail.com>
 * @copyright Copyright (c) 2012, Ryan Chouinard
 * @license MIT License - http://www.opensource.org/licenses/mit-license.php
 * @version 3.0.0-dev
 */

namespace PHPassLib\Hash;

use PHPassLib\Hash;
use PHPassLib\Utilities;
use PHPassLib\Exception\InvalidArgumentException;

/**
 * SHA-1 Crypt Module
 *
 * @package PHPassLib\Hashes
 * @author Ryan Chouinard <rchouinard@gmail.com>
 * @copyright Copyright (c) 2012, Ryan Chouinard
 * @license MIT License - http://www.opensource.org/licenses/mit-license.php
 */
class SHA1Crypt implements Hash
{

    /**
     * Generate a config string from an array.
     *
     * @param array $config Array of configuration options.
     * @return string Configuration string.
     * @throws InvalidArgumentException Throws an InvalidArgumentException if
     *     any passed-in configuration options are invalid.
     */
    public static function genConfig(array $config = array ())
    {
        $defaults = array (
            'rounds' => 40000,
            'salt' => Utilities::encode64(Utilities::genRandomBytes(6)),
        );
        $config = array_merge($defaults, array_change_key_case($config, CASE_LOWER));

        $string = '*1';
        self::validateOptions($config);
        $string = sprintf('$sha1$%d$%s', $config['rounds'], $config['salt']);

        return $string;
    }

    /**
     * Parse a config string into an array.
     *
     * @param string $config Configuration string.
     * @return array Array of configuration options or false on failure.
     */
    public static function parseConfig($config)
    {
        $options = false;
        $matches = array ();
        if (preg_match('/^\$sha1\$(\d+)\$([\.\/0-9A-Za-z]{0,64})\$?/', $config, $matches)) {
            $options = array (
                'rounds' => (int) $matches[1],
                'salt' => $matches[2],
            );

            try {
                self::validateOptions($options);
            } catch (InvalidArgumentException $e) {
                $options = false;
            }
        }

        return $options;
    }

    /**
     * Generate a password hash using a config string.
     *
     * @param string $password Password string.
     * @param string $config Configuration string.
     * @return string Returns the hash string on success. On failure, one of
     *     *0 or *1 is returned.
     */
    public static function genHash($password, $config)
    {
        // Set default hash value to an error string
        $hash = ($config == '*0') ? '*1' : '*0';

        // Extract options from config string
        $matches = array ();
        if (preg_match('/^\$sha1\$(\d+)\$([\.\/0-9A-Za-z]*)\$?/', $config, $matches)) {
            $config = array (
                'rounds' => $matches[1],
                'salt' => $matches[2],
            );
        }

        // If the configuration array isn't populated, return the error string
        if (!is_array($config)) {
            return $hash;
        }

        // Validate config string
        try {
            self::validateOptions($config);
        } catch (InvalidArgumentException $e) {
            return $hash;
        }

        // Calculate the checksum
        $rounds = (int) $config['rounds'];
        $checksum = hash_hmac('sha1', $config['salt'] . '$sha1$' . $config['rounds'], $password, true);
        --$rounds;
        if ($rounds) {
            do {
                $checksum = hash_hmac('sha1', $checksum, $password, true);
            } while (--$rounds);
        }

        // Shuffle the bits around a bit
        $tmp = '';
        foreach (array (2, 1, 0, 5, 4, 3, 8, 7, 6, 11, 10, 9, 14, 13, 12, 17, 16, 15, 0, 19, 18) as $offset) {
            $tmp .= $checksum[$offset];
        }
        $checksum = Utilities::encode64($tmp);

        $hash = self::genConfig($config) . '$' . $checksum;
        return $hash;
    }

    /**
     * Generate a password hash using a config string or array.
     *
     * @param string $password Password string.
     * @param string|array $config Optional config string or array of options.
     * @return string Returns the hash string on success. On failure, one of
     *     *0 or *1 is returned.
     */
    public static function hash($password, $config = array ())
    {
        if (is_array($config)) {
            $config = self::genConfig($config);
        }

        return self::genHash($password, $config);
    }

    /**
     * Verify a password against a hash string.
     *
     * @param string $password Password string.
     * @param string $hash Hash string.
     * @return boolean Returns true if the password matches, false otherwise.
     */
    public static function verify($password, $hash)
    {
        return ($hash === self::hash($password, $hash));
    }

    /**
     * @param array $options
     * @return boolean
     * @throws InvalidArgumentException
     */
    protected static function validateOptions(array $options)
    {
        $options = array_change_key_case($options, CASE_LOWER);
        foreach ($options as $option => $value) switch ($option) {

            case 'rounds':
                if ($value < 1 || $value > 4294967295) {
                    throw new InvalidArgumentException('Rounds must be a number in the range 1 - 4294967295.');
                }
                break;

            case 'salt':
                if (!preg_match('/^[\.\/0-9A-Za-z]{0,64}$/', $value)) {
                    throw new InvalidArgumentException('Salt must be a string matching the regex pattern /[./0-9A-Za-z]{0,64}/.');
                }
                break;

            default:
                break;

        }

        return true;
    }

}