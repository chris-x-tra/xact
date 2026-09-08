<?php
namespace OCA\Xact\Service;

use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext; 

class PagebreakParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        // Reagiert auf das Symbol ⎘ (oder verändern Sie es z. B. zu InlineParserMatch::exact('[[pagebreak]]'))
        //return InlineParserMatch::exact('⎘');
        return InlineParserMatch::string('⎘');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $inlineContext->getCursor()->advanceBy(1);

        // Fügt ein ThematicBreak (<hr>) in den aktuellen Dokumenten-Stream ein
        $inlineContext->getContainer()->appendChild(new ThematicBreak());

        return true;
    }
}
