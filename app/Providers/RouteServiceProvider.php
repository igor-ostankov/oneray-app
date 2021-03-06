<?php namespace App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

use App\Workspace;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App;

class RouteServiceProvider extends ServiceProvider {

	/**
	 * This namespace is applied to the controller routes in your routes file.
	 *
	 * In addition, it is set as the URL generator's root namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'App\Http\Controllers';

	/**
	 * Define your route model bindings, pattern filters, etc.
	 *
	 * @param  \Illuminate\Routing\Router  $router
	 * @return void
	 */
	public function boot(Router $router)
	{
		parent::boot($router);

		$router->bind('workspace', function($value) {
			$currentWs = Workspace::where('domain_prefix', $value)->first();
			if($currentWs) {
				$this->app->instance('CurrentWorkspace', $currentWs);
				$this->app->make('DBConnection');
				view()->share('workspace', $currentWs);
				return $currentWs;
			} else {
				throw new NotFoundHttpException;
			}
		});
	}

	/**
	 * Define the routes for the application.
	 *
	 * @param  \Illuminate\Routing\Router  $router
	 * @return void
	 */
	public function map(Router $router)
	{
		$router->group(['namespace' => $this->namespace], function($router)
		{
			require app_path('Http/routes.php');
		});
	}

}
