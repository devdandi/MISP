<?php
class CidrTool
{
    /** @var array */
    private $ipv4 = [];

    /** @var array */
    private $ipv6 = [];

    public function __construct(array $list)
    {
        $this->filterInputList($list);
    }

    /**
     * @param string $value IPv4 or IPv6 address or range
     * @return false|string
     */
    public function contains($value)
    {
        $valueMask = null;
        if (strpos($value, '/') !== false) {
            list($value, $valueMask) = explode('/', $value);
        }

        $match = false;
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // This code converts IP address to all possible CIDRs that can contains given IP address
            // and then check if given hash table contains that CIDR.
            $ip = ip2long($value);
            // Start from 1, because doesn't make sense to check 0.0.0.0/0 match
            for ($bits = 1; $bits <= 32; $bits++) {
                $mask = -1 << (32 - $bits);
                $needle = long2ip($ip & $mask) . "/$bits";
                if (isset($this->ipv4[$needle])) {
                    $match = $needle;
                    break;
                }
            }

        } elseif (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $value = unpack('n*', inet_pton($value));
            foreach ($this->ipv6 as $lv) {
                if ($this->ipv6InCidr($value, $lv)) {
                    $match = $lv;
                    break;
                }
            }
        }

        if ($match && $valueMask) {
            $matchMask = explode('/', $match)[1];
            if ($valueMask < $matchMask) {
                return false;
            }
        }

        return $match;
    }

    /**
     * Using solution from https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpFoundation/IpUtils.php
     *
     * @param array $ip
     * @param string $cidr
     * @return bool
     */
    private function ipv6InCidr($ip, $cidr)
    {
        list($address, $netmask) = explode('/', $cidr);
        $bytesAddr = unpack('n*', inet_pton($address));

        for ($i = 1, $ceil = ceil($netmask / 16); $i <= $ceil; ++$i) {
            $left = $netmask - 16 * ($i - 1);
            $left = ($left <= 16) ? $left : 16;
            $mask = ~(0xffff >> $left) & 0xffff;
            if (($bytesAddr[$i] & $mask) != ($ip[$i] & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filter out invalid IPv4 or IPv4 CIDR and append maximum netmask if no netmask is given.
     * @param array $list
     */
    private function filterInputList(array $list)
    {
        foreach ($list as $v) {
            $parts = explode('/', $v, 2);
            if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $maximumNetmask = 32;
            } else if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $parts[0] = strtolower($parts[0]);
                $maximumNetmask = 128;
            } else {
                // IP address part of CIDR is invalid
                continue;
            }

            if (!isset($parts[1])) {
                // If CIDR doesnt contains '/', we will consider CIDR as /32 for IPv4 or /128 for IPv6
                $v = "$v/$maximumNetmask";
            } else if ($parts[1] > $maximumNetmask || $parts[1] < 0) {
                // Netmask part of CIDR is invalid
                continue;
            }

            if ($maximumNetmask === 32) {
                $this->ipv4[$v] = true;
            } else {
                $this->ipv6[] = $v;
            }
        }
    }
}


