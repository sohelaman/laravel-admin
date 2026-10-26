<?php

namespace Encore\Admin\Controllers;

use Encore\Admin\Actions\Action;
use Encore\Admin\Actions\GridAction;
use Encore\Admin\Actions\Response;
use Encore\Admin\Actions\RowAction;
use Encore\Admin\Grid\Selectable;
use Encore\Admin\Widgets\Form;
use Exception;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class HandleController extends Controller
{
    /**
     * @param Request $request
     *
     * @return $this|mixed
     */
    public function handleForm(Request $request)
    {
        $form = $this->resolveForm($request);

        if ($errors = $form->validate($request)) {
            return back()->withInput()->withErrors($errors);
        }

        return $form->sanitize()->handle($request);
    }

    /**
     * @param Request $request
     *
     * @throws Exception
     *
     * @return Form
     */
    protected function resolveForm(Request $request)
    {
        if (!$request->has('_form_')) {
            throw new Exception('Invalid form request.');
        }

        $formClass = $request->get('_form_');

        // The class comes from the request, only forms can be resolved.
        if (!is_string($formClass) || !class_exists($formClass) || !is_subclass_of($formClass, Form::class)) {
            throw new Exception('Form ['.e($formClass).'] does not exist.');
        }

        /** @var Form $form */
        $form = app($formClass);

        if (!method_exists($form, 'handle')) {
            throw new Exception("Form method {$formClass}::handle() does not exist.");
        }

        return $form;
    }

    /**
     * @param Request $request
     *
     * @return $this|\Illuminate\Http\JsonResponse
     */
    public function handleAction(Request $request)
    {
        $action = $this->resolveActionInstance($request);

        $model = null;
        $arguments = [];

        if ($action instanceof GridAction) {
            $model = $action->retrieveModel($request);
            $arguments[] = $model;
        }

        if (!$action->passesAuthorization($model)) {
            return $action->failedAuthorization();
        }

        if ($action instanceof RowAction) {
            $action->setRow($model);
        }

        try {
            $response = $action->validate($request)->handle(
                ...$this->resolveActionArgs($request, ...$arguments)
            );
        } catch (Exception $exception) {
            return Response::withException($exception)->send();
        }

        if ($response instanceof Response) {
            return $response->send();
        }
    }

    /**
     * @param Request $request
     *
     * @throws Exception
     *
     * @return Action
     */
    protected function resolveActionInstance(Request $request)
    {
        if (!$request->has('_action')) {
            throw new Exception('Invalid action request.');
        }

        $actionClass = str_replace('_', '\\', (string) $request->get('_action'));

        // The class comes from the request, only actions can be resolved.
        if (!class_exists($actionClass) || !is_subclass_of($actionClass, Action::class)) {
            throw new Exception('Action ['.e($actionClass).'] does not exist.');
        }

        /** @var GridAction $form */
        $action = app($actionClass);

        if (!method_exists($action, 'handle')) {
            throw new Exception("Action method {$actionClass}::handle() does not exist.");
        }

        return $action;
    }

    /**
     * @param Request               $request
     * @param Model|Collection|bool $model
     *
     * @return array
     */
    protected function resolveActionArgs(Request $request, $model = null)
    {
        $args = [$request];

        if (!empty($model)) {
            array_unshift($args, $model);
        }

        return $args;
    }

    /**
     * @param Request $request
     *
     * @return mixed|string|string[]
     */
    public function handleSelectable(Request $request)
    {
        $class = $request->get('selectable');
        $args = $request->get('args', []);

        $class = str_replace('_', '\\', (string) $class);

        // The class comes from the request, only selectables can be created.
        abort_unless(class_exists($class) && is_subclass_of($class, Selectable::class), 404);

        /** @var Selectable $selectable */
        $selectable = new $class(...array_values((array) $args));

        return $selectable->render();
    }

    /**
     * @param Request $request
     *
     * @return mixed|string|string[]
     */
    public function handleRenderable(Request $request)
    {
        $class = $request->get('renderable');
        $key = $request->get('key');

        $class = str_replace('_', '\\', (string) $class);

        // The class comes from the request, only renderables can be created.
        abort_unless(class_exists($class) && is_subclass_of($class, Renderable::class), 404);

        /** @var Renderable $renderable */
        $renderable = new $class();

        return $renderable->render($key);
    }
}
