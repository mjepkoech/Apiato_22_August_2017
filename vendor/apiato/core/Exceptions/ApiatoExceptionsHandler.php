<?php

namespace Apiato\Core\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as LaravelExceptionHandler;
use Illuminate\Support\Facades\Log;

/**
 * Class ApiatoExceptionsHandler
 *
 * A.K.A (app/Exceptions/Handler.php)
 *
 * @author  Mahmoud Zalt  <mahmoud@zalt.me>
 */
class ApiatoExceptionsHandler extends LaravelExceptionHandler
{

    const DEFAULT_MESSAGE = 'Oops something went wrong.';
    const DEFAULT_CODE = 400;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [

    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception $exception
     *
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception               $exception
     *
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if ($request->expectsJson()) {

            return $this->renderJson($exception, $request);

        }

        return parent::render($request, $exception);
    }

    /**
     * @param $exception
     *
     * @return  \Illuminate\Http\JsonResponse
     */
    private function renderJson($exception, $request)
    {
        // TODO: needs refactoring..

        // default response message
        $responseMessage['errors'] = self::DEFAULT_MESSAGE;

        // If this exception is an instance of HttpException get the HTTP status else use the default
        $responseMessage['status_code'] = $this->isHttpException($exception) ? $exception->getStatusCode() : self::DEFAULT_CODE;

        $responseMessage['message'] = $exception->getMessage();

        // If debugging enabled, add the exception class name, message and stack trace to response
        if (config('app.debug')) {
            $responseMessage['exception'] = get_class($exception); // Reflection might be better here
        }

        // if API debug is enabled
        if (config('apiato.api.debug')) {
            // include the trace in the response
            $responseMessage['trace'] = json_encode($exception->getTrace());
            // log the error
            Log::error($exception);
        }

        //-----------------------------

        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            $responseMessage['status_code'] = 422;
            $responseMessage['errors'] = $exception->validator->errors()->getMessages();
        }

        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            $responseMessage['status_code'] = 401;
            $responseMessage['errors'] = 'Missing or invalid Access Token!';
        }

        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            $responseMessage['status_code'] = 403;
            $responseMessage['errors'] = 'You have no access to this resource!';
        }

        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            $responseMessage['status_code'] = 405;
            $responseMessage['errors'] = '405 Method Not Allowed.';
        }

        // Return a JSON response with the response array and status code
        return response()->json($responseMessage, $responseMessage['status_code']);
    }


    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request                 $request
     * @param  \Illuminate\Auth\AuthenticationException $exception
     *
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest('login');
    }

}
