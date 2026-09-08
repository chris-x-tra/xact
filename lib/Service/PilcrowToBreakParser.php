<?php
namespace OCA\Xact\Service;

use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class PilcrowToBreakParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        // Reagiert exakt auf das Pilcrow-Zeichen (UTF-8)
        //return InlineParserMatch::exact('¶');
        return InlineParserMatch::string('¶');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        // Zeichen im Stream überspringen
        $inlineContext->getCursor()->advanceBy(1);
        
        // Fügt ein hartes BR-Element ein
        $inlineContext->getContainer()->appendChild(new Newline(Newline::HARDBREAK));


        return true;
    }
}
