<?php

namespace Neos\Setup\Infrastructure\Healthcheck;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Utility\Ip as IpUtility;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;
use Neos\Setup\Domain\WebEnvironment;

class TrustedProxiesHealthcheck implements HealthcheckInterface
{
    /**
     * Centralized list of reverse proxy headers with their configuration mapping categories
     * Header name => mappingKey (clientIp, host, port, proto) or null for detection-only headers
     * Priority is determined by array order for headers with the same mappingKey
     */
    private const REVERSE_PROXY_HEADERS = [
        // Standard reverse proxy headers with mapping
        'X-Forwarded-For' => 'clientIp',
        'X-Forwarded-Host' => 'host',
        'X-Forwarded-Port' => 'port',
        'X-Forwarded-Proto' => 'proto',
        'X-Real-IP' => 'clientIp',
        'Forwarded' => null, // RFC 7239 - detection only

        // Additional clientIp headers (priority order)
        'True-Client-IP' => 'clientIp',
        'X-Client-IP' => 'clientIp',
        'Client-IP' => 'clientIp',

        // Cloudflare
        'CF-Connecting-IP' => 'clientIp',
        'CF-Visitor' => null,
        'CF-RAY' => null,
        'CF-IPCountry' => null,

        // AWS
        'X-Amzn-Trace-Id' => null,
        'X-Amz-Cf-Id' => null,
        'CloudFront-Viewer-Address' => 'clientIp',

        // Google Cloud
        'X-Cloud-Trace-Context' => null,

        // Azure
        'X-Azure-ClientIP' => 'clientIp',
        'X-ARR-ClientIP' => 'clientIp',

        // Fastly / Other CDNs
        'Fastly-Client-IP' => 'clientIp',
        'X-Forwarded-Ssl' => null,
        'X-Original-Forwarded-For' => 'clientIp',
        'X-Original-Host' => 'host',
    ];

    public function __construct(
        private ConfigurationManager $configurationManager,
    ) {
    }

    public function getTitle(): string
    {
        return 'Trusted Proxies Configuration';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        if ($environment->executionEnvironment instanceof WebEnvironment) {
            $trustedProxiesConfig = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow.http.trustedProxies');
            $configuredHeaders = $trustedProxiesConfig['headers'] ?? [];
            $configuredProxies = $trustedProxiesConfig['proxies'] ?? [];

            if (is_string($configuredProxies)) {
                $configuredProxies = array_map('trim', explode(',', $configuredProxies));
            }

            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

            // Check if any reverse proxy header is present
            $detectedProxyHeaders = [];
            foreach (self::REVERSE_PROXY_HEADERS as $header => $mappingKey) {
                if (isset($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $header))])) {
                    $detectedProxyHeaders[] = $header;
                }
            }

            if (count($detectedProxyHeaders) > 0) {
                $message = "Reverse proxy headers detected: " . implode(', ', $detectedProxyHeaders) . "<br /><br />";
            } else {
                $message = "No reverse proxy headers detected.<br /><br />";
            }


            if (!empty($detectedProxyHeaders)) {
                // Reverse proxy headers detected
                // -> config is OK ($isRemoteAddrTrusted==true) if the current REMOTE_ADDR matches any configured trusted proxy
                $isProxyConfigured = !empty($configuredProxies);
                $isRemoteAddrTrusted = false;

                if ($isProxyConfigured && $remoteAddr) {
                    $isRemoteAddrTrusted = self::matchesProxyPattern($remoteAddr, $configuredProxies);
                }

                if (!$isProxyConfigured || !$isRemoteAddrTrusted) {
                    // Trusted proxies not configured or don't match REMOTE_ADDR

                    if (!$isProxyConfigured) {
                        $message .= "<b>Trusted proxies are not configured.</b> ";
                    } else {
                        $message .= "The current REMOTE_ADDR {$remoteAddr} does not match any configured trusted proxies "  . implode(',', $configuredProxies) . ". ";
                    }

                    $message .= "You need to configure trusted proxies to ensure URLs can be properly built.<br /><br />";
                    $message .= "Configure via Settings.yaml:<br /><br />";
                    $message .= "<pre>Neos:\n";
                    $message .= "  Flow:\n";
                    $message .= "    http:\n";
                    $message .= "      trustedProxies:\n";

                    if ($remoteAddr) {
                        $message .= "        proxies: ['{$remoteAddr}']\n";
                    } else {
                        $message .= "        proxies: ['<your-proxy-ip>']\n";
                    }

                    // Generate headers configuration mapping based on detected headers
                    $headersMapping = self::generateHeadersMapping($detectedProxyHeaders);
                    if (!empty($headersMapping)) {
                        $message .= "        headers:\n";
                        foreach ($headersMapping as $key => $header) {
                            $message .= "          {$key}: '{$header}'\n";
                        }
                    }

                    $message .= "    </pre>\n";


                    $message .= "Alternatively, set the FLOW_HTTP_TRUSTED_PROXIES={$remoteAddr} environment variable.<br />";
                    $message .= 'See <a href="https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/Http.html#trusted-proxies">Documentation on trusted proxies</a> for further details.';

                    return new Health($message, Status::WARNING());
                } else {
                    // Trusted proxies properly configured
                    return new Health(
                        "Reverse proxy configuration appears correct.<br />" .
                        "Detected headers: " . implode(', ', $detectedProxyHeaders) . "<br />" .
                        "REMOTE_ADDR ({$remoteAddr}) is configured as a trusted proxy.",
                        Status::OK()
                    );
                }
            } else {
                // No reverse proxy headers detected
                if (!empty($configuredProxies)) {
                    return new Health(
                        "No reverse proxy headers detected in the request, but trusted proxies are configured.<br /><br />" .
                        "If you are not running behind a reverse proxy, you should remove the trusted proxies configuration in Settings.yaml, path Neos.Flow.http.trustedProxies.proxies; and remove the environment variable FLOW_HTTP_TRUSTED_PROXIES.<br />" .
                        "Otherwise, ensure your reverse proxy is properly configured to send the expected headers.",
                        Status::WARNING()
                    );
                } else {
                    return new Health(
                        "No reverse proxy headers detected. Running in direct connection mode.<br />" .
                        "Trusted proxies configuration is not set, which is correct for this setup.",
                        Status::OK()
                    );
                }
            }
        }

        // Fallback for CLI environment
        return new Health(
            <<<'MSG'
                If you are behind a reverse proxy, you need to configure trusted proxies, to ensure URLs can be
                properly built. This is possible via Settings.yaml at Neos.Flow.http.trustedProxies,
                or the FLOW_HTTP_TRUSTED_PROXIES environment variable.

                See https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/Http.html#trusted-proxies
                for further details.

                You can also run the web-based setup wizard at /setup, which checks if trusted proxies are set up correctly.
                MSG,
            Status::UNKNOWN()
        );
    }

    private static function matchesProxyPattern(string $remoteAddr, array $configuredProxies): bool
    {
        foreach ($configuredProxies as $ipPattern) {
            if ($ipPattern === '*') {
                return true;
            }
            if (IpUtility::cidrMatch($remoteAddr, $ipPattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate headers Neos config mapping based on detected headers
     */
    private static function generateHeadersMapping(array $detectedHeaders): array
    {
        $mapping = [];

        // Process headers in priority order (as defined in REVERSE_PROXY_HEADERS)
        // First match wins for each mappingKey
        foreach (self::REVERSE_PROXY_HEADERS as $header => $mappingKey) {
            // Skip headers without a mapping key (detection only) or already mapped keys (as these then have higher priorities)
            if ($mappingKey === null || isset($mapping[$mappingKey])) {
                continue;
            }

            if (in_array($header, $detectedHeaders, true)) {
                $mapping[$mappingKey] = $header;
            }
        }

        return $mapping;
    }
}
