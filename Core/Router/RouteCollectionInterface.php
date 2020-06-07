<?php
namespace QTCS\Router;

interface RouteCollectionInterface {
	public function add(string $from, $to, array $options = null);
	public function addPlaceholder($placeholder, string $pattern = null);
	public function setDefaultNamespace(string $value);
	public function setDefaultController(string $value);
	public function setDefaultMethod(string $value);
	public function setTranslateURIDashes(bool $value);
	public function setAutoRoute(bool $value): self;
	public function set404Override($callable = null): self;
	public function get404Override();
	public function getDefaultController();
	public function getDefaultMethod();
	public function shouldTranslateURIDashes();
	public function shouldAutoRoute();
	public function getRoutes();
	public function getHTTPVerb();
	public function reverseRoute(string $search, ...$params);
	public function isRedirect(string $from): bool;
	public function getRedirectCode(string $from): int;
}