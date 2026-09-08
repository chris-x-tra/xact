<?php
namespace OCA\Xact\Service;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

class CustomShortcutsExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new PilcrowToBreakParser());
        $environment->addInlineParser(new PagebreakParser());
    }
}
