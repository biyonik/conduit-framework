<?php

declare(strict_types=1);

namespace Conduit\Validation\Traits;

/**
 * IpValidationTrait
 * 
 * Provides IP address validation functionality.
 * 
 * @package Conduit\Validation\Traits
 */
trait IpValidationTrait
{
    /**
     * Validate IPv4 address
     * 
     * @param string $ip IP address
     * @return bool
     */
    protected function isValidIpv4(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Validate IPv6 address
     * 
     * @param string $ip IP address
     * @return bool
     */
    protected function isValidIpv6(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Validate IP address (v4 or v6)
     * 
     * @param string $ip IP address
     * @return bool
     */
    protected function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if IP is in a given range (CIDR notation)
     * 
     * @param string $ip IP address
     * @param string $range CIDR range (e.g., '192.168.1.0/24')
     * @return bool
     */
    protected function isIpInRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $mask] = explode('/', $range);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int) $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if IP is private
     * 
     * @param string $ip IP address
     * @return bool
     */
    protected function isPrivateIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
