<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Controller;

use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * CSRF validation for POST form submissions.
 *
 * Kanboard's BaseController::checkCSRFParam() reads from $_GET, which
 * does not work for POST form submissions. This trait provides a
 * checkCSRFToken() method that reads the token from $_POST via
 * $this->request->getValues().
 */
trait CsrfTrait
{
    protected function checkCSRFToken(): void
    {
        $values = $this->request->getValues();
        $token  = $values['csrf_token'] ?? '';

        if (! $this->token->validateCSRFToken($token)) {
            throw new AccessForbiddenException();
        }
    }
}
