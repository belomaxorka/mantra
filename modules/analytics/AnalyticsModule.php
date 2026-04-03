<?php declare(strict_types=1);

/**
 * Analytics Module - Example of adding tracking scripts
 *
 * Demonstrates:
 * - Adding scripts to footer via theme.footer hook
 * - Using module settings
 * - Conditional script loading
 */

use Module\Module;

class AnalyticsModule extends Module
{
    public function init(): void
    {
        // Hook into theme footer to add analytics scripts
        $this->hook('theme.footer', [$this, 'addAnalyticsScripts']);
    }

    /**
     * Add analytics scripts to footer
     */
    public function addAnalyticsScripts($content)
    {
        $scripts = [];

        // Google Analytics
        $gaId = $this->settings()->get('google_analytics_id');
        if ($gaId) {
            $scripts[] = $this->getGoogleAnalyticsScript($gaId);
        }

        // Yandex Metrika
        $ymId = $this->settings()->get('yandex_metrika_id');
        if ($ymId) {
            $scripts[] = $this->getYandexMetrikaScript($ymId);
        }

        // Custom tracking code
        $customCode = $this->settings()->get('custom_code');
        if ($customCode) {
            $scripts[] = "\n    " . $customCode;
        }

        if (!empty($scripts)) {
            return $content . "\n    " . implode("\n    ", $scripts);
        }

        return $content;
    }

    /**
     * Get Google Analytics script
     */
    private function getGoogleAnalyticsScript($gaId)
    {
        $gaId = e($gaId);
        return <<<HTML
            <!-- Google Analytics -->
                <script async src="https://www.googletagmanager.com/gtag/js?id={$gaId}"></script>
                <script>
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('js', new Date());
                    gtag('config', '{$gaId}');
                </script>
            HTML;
    }

    /**
     * Get Yandex Metrika script
     */
    private function getYandexMetrikaScript($ymId)
    {
        $ymId = e($ymId);
        return <<<HTML
            <!-- Yandex.Metrika -->
                <script type="text/javascript">
                    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                    m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
                    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
                    ym({$ymId}, "init", {clickmap:true, trackLinks:true, accurateTrackBounce:true});
                </script>
                <noscript><div><img src="https://mc.yandex.ru/watch/{$ymId}" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
            HTML;
    }
}
