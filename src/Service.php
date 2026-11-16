<?php
namespace MiniPaviService;


final class Service {

	public function __construct(
		private MiniPaviClient $client,
		private Router $router,
	) {
	}

	public function run(): void {

		$this->client->start();

		if ($this->client->fctn() == Controller::FCTN_CONNECT || $this->client->fctn() == Controller::FCTN_DIRECT_CONNECT) {
			$context = new Context();
			$this->client->clearContent();
			trigger_error("[MiniPaviService] CNX");
		} else {
			$context = Context::unserialize($this->client->context());		// Récupération du contexte utilisateur
		}

		$context->route ??= $this->router->defaultRoute();

		if ($this->client->fctn() == Controller::FCTN_DISCONNECT) {
				trigger_error("[MiniPaviService] DECO");
				return;
		}

		while(true) {
			$route = $context->route;

			$controller = $this->router->route($route);
			$controller->setState($context->controllerState($route) ?? Controller::STATE_INIT);
			$controller->setData($context->controllerData($route));

			$response = $controller->run();

			$context->setControllerState($route, $controller->state());
			$context->setControllerData($route, $controller->data());
			
			$response->execute($this->client, $context);

			if ($response->interrupts()) {
				return;
			}
		}
	}
}
