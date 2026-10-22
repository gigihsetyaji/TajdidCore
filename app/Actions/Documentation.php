<?php
declare(strict_types=1);
namespace App\Actions;
use Tajdid\Core\Config;
use Tajdid\Core\Http\Response;
use Tajdid\Core\View;
final class Documentation
{
    public function __invoke(View $view, Config $config): Response
    {
        return $view->render('documentation', ['name' => $config->get('app.name')]);
    }
}
