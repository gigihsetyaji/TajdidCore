<?php
declare(strict_types=1);
namespace Tajdid\Core\Validation;
use Tajdid\Core\Http\HttpException;
final class ValidationException extends HttpException
{
    public function __construct(public readonly array $errors) { parent::__construct(422, 'Validation failed'); }
}
