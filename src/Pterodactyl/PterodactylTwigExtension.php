<?php
namespace App\Pterodactyl;

use ClientX\Translator\Translater;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

class PterodactylTwigExtension extends AbstractExtension {

    private Translater $translater;
    public function __construct(Translater $translater)
    {
        $this->translater = $translater;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('pterodactyl_server_status', [$this, 'status'], ['is_safe' => ['html']]),
            new TwigFilter('pterodactyl_server_cpucolor', [$this, 'color'], ['is_safe' => ['html']]),
        ];
    }

    public function getTests()
    {
        return [
            new TwigTest('pterodactyl_online', [$this, 'online']),
            new TwigTest('pterodactyl_offline', [$this, 'offline']),
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
        return $status->current_state === 'online';
    }
    
    public function offline($status) {
        return $status->current_state ==='offline';
    }

    public function status($status, bool $html = true){
        $value = $status->current_state;
        $class = null;
        $content = null;
        switch ($value) {
            case'online':
                $class = "success";
                $content = $this->translater->trans("pterodactyl.online");
                break;
                
            case'starting':
                $class = "success";
                $content = "Starting";
                break;
                
            case'stopping':
                $class = "success";
                $content = "Stopping";
                break;
            case 'offline':
                $class = "danger";
                $content = $this->translater->trans("pterodactyl.offline");
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