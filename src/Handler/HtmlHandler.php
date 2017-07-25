<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler\Handler;

/**
 * Class TemplateHandler
 * @package CoiSA\ErrorHandler\Handler
 */
class HtmlHandler implements HandlerInterface
{
    /**
     * HtmlHandler constructor.
     *
     * @param string $template
     * @param string $layout optional
     * @param array $variables optional
     */
    public function __construct(string $template, string $layout = null, array $variables = [])
    {

    }

    /**
     * @param \Throwable $throwable
     * @return int|null
     */
    public function __invoke(\Throwable $throwable): ?int
    {
        // @TODO header content-type
        // @TODO json_encode body

        return $throwable->getCode();
    }

    private function render()
    {

    }
}