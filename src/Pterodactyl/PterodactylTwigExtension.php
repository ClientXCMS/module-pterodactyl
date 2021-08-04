<?php
namespace App\Pterodactyl;

use ClientX\Translator\Translater;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

class PterodactylTwigExtension extends AbstractExtension {


    private Translater $translater;
    const ONLINE = "running";
    const OFFLINE = "offline";
    const STARTING = "starting";
    const STOPPING = "stopping";
    const PREFIX = "pterodactyl";
    public function __construct(Translater $translater)
    {
        $this->translater = $translater;
    }

    public function getFilters()
    {
        return [
            new TwigFilter(self::PREFIX .'_server_status', [$this, 'status'], ['is_safe' => ['html']]),
            new TwigFilter(self::PREFIX .'_server_cpucolor', [$this, 'color'], ['is_safe' => ['html']]),
        ];
    }

    public function getTests()
    {
        return [
            new TwigTest(self::PREFIX.'_online', [$this, 'online']),
            new TwigTest(self::PREFIX.'_offline', [$this, 'offline']),
        ];
    }

    public function color($porcent){
        if ($porcent > 90){
            return "danger";
        }
        if ($porcent > 80){
            return "warning";
        }
        return "info";
    }
    public function online($status) {
        return $status->current_state === self::ONLINE || $status->current_state === self::STARTING;
    }
    
    public function offline($status) {
        return $status->current_state === self::OFFLINE || $status->current_state === self::STOPPING;
    }

    public function status($status, bool $html = true){
        $value = $status->current_state;
        $class = null;
        $content = null;
        switch ($value) {
            case self::ONLINE:
                $class = "success";
                $content = $this->translater->trans(self::PREFIX . ".online");
                break;
                
            case self::STARTING:
                $class = "success";
                $content = "Starting";
                break;
                
            case self::STOPPING:
                $class = "success";
                $content = "Stopping";
                break;
            case self::STOPPING:
                $class = "danger";
                $content = $this->translater->trans(self::PREFIX .".offline");
                break;
            default:
                $class = "danger";
                $content = "ERROR";
        }
        if ($html) {
            return sprintf("<span class='badge badge-%s'>%s</span>", $class, $content);
        }
        return $content;
    }
}
