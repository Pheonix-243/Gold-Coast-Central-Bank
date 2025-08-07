<?php
class LocationService {
    private $apiKey = "d6cc6e6e424a3b318f36369f65851c61";
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey;
    }
    
    public function getLocationFromIP($ip) {
        if ($this->isPrivateIP($ip)) {
            return null;
        }
        
        // Try free IPAPI first
        $location = $this->getFromIPAPI($ip);
        if ($location) {
            return $location;
        }
        
        // Fallback to ipinfo.io if we have an API key
        if ($this->apiKey) {
            return $this->getFromIPInfo($ip);
        }
        
        return null;
    }
    
    private function getFromIPAPI($ip) {
        $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,lat,lon,isp,org,as,mobile,proxy,hosting";
        
        try {
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            
            if ($data && $data['status'] === 'success') {
                return [
                    'ip' => $ip,
                    'country' => $data['country'] ?? 'Unknown',
                    'country_code' => $data['countryCode'] ?? '',
                    'region' => $data['regionName'] ?? 'Unknown',
                    'city' => $data['city'] ?? 'Unknown',
                    'isp' => $data['isp'] ?? 'Unknown',
                    'mobile' => $data['mobile'] ?? false,
                    'proxy' => $data['proxy'] ?? false,
                    'hosting' => $data['hosting'] ?? false
                ];
            }
        } catch (Exception $e) {
            error_log("IPAPI error: " . $e->getMessage());
        }
        
        return null;
    }
    
    private function getFromIPInfo($ip) {
        $url = "https://ipinfo.io/{$ip}?token={$this->apiKey}";
        
        try {
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            
            if ($data && !isset($data['error'])) {
                $loc = explode(',', $data['loc'] ?? '0,0');
                return [
                    'ip' => $ip,
                    'country' => $data['country'] ?? 'Unknown',
                    'region' => $data['region'] ?? 'Unknown',
                    'city' => $data['city'] ?? 'Unknown',
                    'loc' => ['lat' => $loc[0] ?? 0, 'lon' => $loc[1] ?? 0],
                    'org' => $data['org'] ?? 'Unknown'
                ];
            }
        } catch (Exception $e) {
            error_log("IPInfo error: " . $e->getMessage());
        }
        
        return null;
    }
    
    private function isPrivateIP($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
?>