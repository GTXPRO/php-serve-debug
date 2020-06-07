<?php

namespace QTCS\View;

interface RendererInterface
{
	public function render(string $view, array $options = null, bool $saveData = false): string;
	public function renderString(string $view, array $options = null, bool $saveData = false): string;
	public function setData(array $data = [], string $context = null);
	public function setVar(string $name, $value = null, string $context = null);
	public function resetData();
}
