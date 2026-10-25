<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Context;

use Maispace\MaiTurbo\ViewHelpers\ContextViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;

class ContextStack
{
    /**
     * @var MaiTurboContext[]
     */
    private array $stack = [];
    private readonly self $currentStack;

    public function __construct(ViewHelperVariableContainer $variableContainer)
    {
        $this->currentStack = $variableContainer->get(
            ContextViewHelper::class,
            ContextViewHelper::CURRENT_CONTEXT,
        ) ?? $this;
        if ($this->currentStack === $this) {
            $variableContainer->add(
                ContextViewHelper::class,
                ContextViewHelper::CURRENT_CONTEXT,
                $this,
            );
        }
    }

    public function push(MaiTurboContext $context): void
    {
        $this->currentStack->stack[] = $context;
    }

    public function pop(): MaiTurboContext
    {
        if (count($this->currentStack->stack) < 1) {
            throw new \LogicException('Can not pop off context from empty stack', 1671619657);
        }

        return array_pop($this->currentStack->stack);
    }

    public function current(): ?MaiTurboContext
    {
        if ($this->currentStack->stack === []) {
            return null;
        }

        return $this->currentStack->stack[array_key_last($this->currentStack->stack)];
    }
}
