<?php

namespace MiniPaviService;

use MiniPaviService\Controller\Response;

interface Controller {

    public const STATE_INIT = 'init';
    public const STATE_DISPLAY = 'display';
    public const STATE_INIT_INPUT = 'init-input';
    public const STATE_READ_INPUT = 'read-input';

    public const KEY_REFRESH = 'REPETITION';
    public const KEY_GUIDE = 'GUIDE';
    public const KEY_SEND = 'ENVOI';
    public const KEY_NEXT = 'SUITE';
    public const KEY_PREVIOUS = 'RETOUR';
    public const KEY_SUMMARY = 'SOMMAIRE';
    public const FCTN_CONNECT = 'CNX';
    public const FCTN_DIRECT_CONNECT = 'DIRECTCNX';
    public const FCTN_DISCONNECT = 'FIN';

    public function run(): Response;

    // State, and data the controller needs besides it (e.g. the page of the directory), kept
    // in the user context from one request of the gateway to the next
    public function setState(string $state): void;
    public function state(): string;
    public function setData(array $data): void;
    public function data(): array;
}