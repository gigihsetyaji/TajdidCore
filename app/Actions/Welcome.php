<?php
declare(strict_types=1);
namespace App\Actions;
use Tajdid\Core\Config;
use Tajdid\Core\View;
use Tajdid\Core\Http\Response;
final class Welcome
{
    public function __invoke(View $view, Config $config): Response { return $view->render('welcome', ['name' => $config->get('app.name')]); }
}
