<?php

namespace MiniPaviService;

final class ServiceRunner {
    public function __construct(private Service $service) {
    }

    public function run(): void {

        $this->check();

        $this->service->run();
    }

    private function check(): void {
        if (strpos(@$_SERVER['HTTP_USER_AGENT'],'MiniPAVI') === false) {
			$this->redirectBrowser();
			exit;
		}
    }

    private function redirectBrowser(): void {
		$serviceUrl = 'http://'.(getenv('SERVICES_INTERNAL_HOST') ?: 'services').$_SERVER['REQUEST_URI'];
		$gw = rtrim(getenv('GATEWAY_URL'),'/').'/?url='.urlencode($serviceUrl);
		header('Location: '.getenv('EMULATOR_URL').'index.php?gw='.urlencode($gw));
	}
}
