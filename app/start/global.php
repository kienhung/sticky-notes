<?php

/*
|--------------------------------------------------------------------------
| Register The Laravel Class Loader
|--------------------------------------------------------------------------
|
| In addition to using Composer, you may use the Laravel class loader to
| load your controllers and models. This is useful for keeping all of
| your classes in the "global" namespace without Composer updating.
|
*/

ClassLoader::addDirectories(array(

	app_path().'/commands',
	app_path().'/controllers',
	app_path().'/models',
	app_path().'/database/seeds',

));

/*
|--------------------------------------------------------------------------
| Application Error Logger
|--------------------------------------------------------------------------
|
| Here we will configure the error logger setup for the application which
| is built on top of the wonderful Monolog library. By default we will
| build a rotating log file setup which creates a new file each day.
|
*/

$logFile = 'log-'.php_sapi_name().'.txt';

Log::useDailyFiles(storage_path().'/logs/'.$logFile);

/*
|--------------------------------------------------------------------------
| Maintenance Mode Handler
|--------------------------------------------------------------------------
|
| The "down" Artisan command gives you the ability to put an application
| into maintenance mode. Here, you will define what is displayed back
| to the user if maintenace mode is in effect for this application.
|
*/

App::down(function()
{
	return Response::make("Be right back!", 503);
});

/*
|--------------------------------------------------------------------------
| Require The Filters File
|--------------------------------------------------------------------------
|
| Next we will load the filters file for the application. This gives us
| a nice separate location to store our route and application filter
| definitions instead of putting them all in the main routes file.
|
*/

require app_path().'/filters.php';

/*
|--------------------------------------------------------------------------
| Set the configured site locale
|--------------------------------------------------------------------------
|
| Set the active site locale as defined in the general config data.
|
*/

App::setLocale(Site::config('general')->lang);

/*
|--------------------------------------------------------------------------
| Sticky Notes auth methods
|--------------------------------------------------------------------------
|
| Define the handlers for sticky-notes authentication requests.
|
*/

Auth::extend('stickynotesdb', function()
{
	$model = Config::get('auth.model');
	$crypt = PHPass::make();

	return new Illuminate\Auth\Guard(
		new StickyNotes\Auth\StickyNotesDBUserProvider($model, $crypt),
		App::make('session')
	);
});

Auth::extend('stickynotesldap', function()
{
	$model = Config::get('auth.model');
	$auth = Site::config('auth');

	return new Illuminate\Auth\Guard(
		new StickyNotes\Auth\StickyNotesLDAPUserProvider($model, $auth),
		App::make('session')
	);
});

/*
|--------------------------------------------------------------------------
| Blade code tags
|--------------------------------------------------------------------------
|
| Define the custom blade tags to handle code such as assignment.
|
*/

Blade::extend(function($value)
{
	return preg_replace('/\{\?(.+)\?\}/', '<?php ${1} ?>', $value);
});

/*
|--------------------------------------------------------------------------
| Handle application errors
|--------------------------------------------------------------------------
|
| Shows custom screens for app errors. This is mainly done to show a
| friendly error message and to throw errors with ease from the view.
|
*/

App::error(function($exception, $code)
{
	$type = get_class($exception);

	// Firstly, log the error
	Log::error($exception);

	// Set code based on exception
	switch ($type)
	{
		case 'Illuminate\Session\TokenMismatchException':
			$code = 403;
			break;

		case 'Illuminate\Database\Eloquent\ModelNotFoundException':
		case 'InvalidArgumentException':
			$code = 404;
			break;
	}

	// Set message based on code
	switch ($code)
	{
		case 401:
		case 403:
		case 404:
		case 405:
		case 418:
			$data['errCode'] = $code;
			break;

		default:
			if (Config::get('app.debug'))
			{
				return;
			}
			else
			{
				// We check if flushing the cache will solve the problem
				if ( ! Input::has('e'))
				{
					Cache::flush();

					return Redirect::to('?e=1');
				}

				$data['errCode'] = 'default';
			}
			break;
	}

	// For regular requests, we show a nice and pretty error screen
	// When in the API, just die on the user
	if (Request::segment(1) == 'api')
	{
		$message = Lang::get('errors.'.$data['errCode']);

		return Response::make($message, $code);
	}
	else
	{
		return Response::view('common/error', $data, $code);
	}
});

/*
|--------------------------------------------------------------------------
| Trust proxy headers
|--------------------------------------------------------------------------
|
| Checks if the site is behind a proxy server (or a load balancer) and
| set whether to trust the client IP sent in the request that comes via
| the proxy intermediary.
|
*/

if (Site::config('general')->proxy)
{
	// Trust the client proxy address
	Request::setTrustedProxies(array(Request::getClientIp()));

	// Trust the client IP header
	Request::setTrustedHeaderName(\Symfony\Component\HttpFoundation\Request::HEADER_CLIENT_IP, 'X-Forwarded-For');

	// Trust the client protocol header
	Request::setTrustedHeaderName(\Symfony\Component\HttpFoundation\Request::HEADER_CLIENT_PROTO, 'X-Forwarded-Proto');
}

/*
|--------------------------------------------------------------------------
| Run cron tasks
|--------------------------------------------------------------------------
|
| Trigger cron jobs that will run every N minutes.
|
*/

Cron::run();
