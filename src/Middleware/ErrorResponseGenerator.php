<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Stratigility\Utils;

class ErrorResponseGenerator
{
    const TEMPLATE_DEFAULT = 'error::error';

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * @var string
     */
    private $stackTraceTemplate = <<<'EOT'
%s raised in file %s line %d:
Message: %s
Stack Trace:
%s

EOT;

    /**
     * @var string
     */
    private $template;

    /**
     * @param bool $isDevelopmentMode
     * @param null|TemplateRendererInterface $renderer
     * @param string $template
     */
    public function __construct(
        bool $isDevelopmentMode = false,
        TemplateRendererInterface $renderer = null,
        string $template = self::TEMPLATE_DEFAULT
    ) {
        $this->debug     = (bool) $isDevelopmentMode;
        $this->renderer  = $renderer;
        $this->template  = $template;
    }

    /**
     * @param Throwable $e
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function __invoke(
        Throwable $e,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        $response = $response->withStatus(Utils::getStatusCode($e, $response));

        if ($this->renderer) {
            return $this->prepareTemplatedResponse($e, $request, $response);
        }

        return $this->prepareDefaultResponse($e, $response);
    }

    /**
     * @param Throwable $e
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function prepareTemplatedResponse(
        Throwable $e,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        $templateData = [
            'response' => $response,
            'request'  => $request,
            'uri'      => (string) $request->getUri(),
            'status'   => $response->getStatusCode(),
            'reason'   => $response->getReasonPhrase(),
        ];

        if ($this->debug) {
            $templateData['error'] = $e;
        }

        $response->getBody()->write(
            $this->renderer->render($this->template, $templateData)
        );

        return $response;
    }

    /**
     * @param Throwable $e
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function prepareDefaultResponse(Throwable $e, ResponseInterface $response) : ResponseInterface
    {
        $message = 'An unexpected error occurred';

        if ($this->debug) {
            $message .= "; strack trace:\n\n" . $this->prepareStackTrace($e);
        }

        $response->getBody()->write($message);

        return $response;
    }

    /**
     * Prepares a stack trace to display.
     *
     * @param Throwable $e
     * @return string
     */
    private function prepareStackTrace(Throwable $e) : string
    {
        $message = '';
        do {
            $message .= sprintf(
                $this->stackTraceTemplate,
                get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage(),
                $e->getTraceAsString()
            );
        } while ($e = $e->getPrevious());

        return $message;
    }
}
