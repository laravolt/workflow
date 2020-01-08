<?php

declare(strict_types=1);

namespace Laravolt\Workflow\Controllers;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Laravolt\Workflow\Models\ProcessInstanceHistory;
use Laravolt\Jasper\Jasper;
use Laravolt\Workflow\Contracts\Workflow;
use Laravolt\Workflow\Entities\Module;
use Laravolt\Workflow\Presenters\TaskForm;
use Laravolt\Workflow\Requests\BasicRequest;

class ProcessController extends Controller
{
    use AuthorizesRequests;

    protected $workflow;

    protected $headers = [
        'pdf' => ['application/pdf'],
        'docx' => 'application/octet-stream',
        'html' => 'text/html',
    ];

    /**
     * ProcessController constructor.
     */
    public function __construct(Workflow $workflow)
    {
        $this->workflow = $workflow;
    }

    public function index(Module $module)
    {
        $this->authorize('view', $module->getModel());

        $view = $module->view['index'] ?? 'camunda::process.index';

        return $module->table->view($view, compact('module'));
    }

    public function create(Module $module)
    {
        $this->authorize('create', $module->getModel());

        $view = $module->view['create'] ?? 'camunda::process.create';

        try {
            $form = $this->workflow->createStartForm($module);

            return view($view, compact('form', 'module'));
        } catch (ClientException $e) {
            abort($e->getCode(), $e->getMessage());
        }
    }

    public function store(Module $module, BasicRequest $request)
    {
        $this->authorize('create', $module->getModel());

        try {
            $form = $this->workflow->createStartForm($module);

            $processInstance = $this->workflow->startProcess(
                $module,
                $request->all()
            );

            $nextForm = TaskForm::make($module, $processInstance->currentTask());

            $message = __('camunda::message.process.started',
                ['current_task' => $form->title(), 'next_task' => $nextForm->title()]);

            return redirect()
                ->route('camunda::process.show', [$module->id, $processInstance->id])
                ->withSuccess($message);
        } catch (ClientException $e) {
            abort($e->getCode(), $e->getMessage());
        }
    }

    public function show(Module $module, $processInstanceId)
    {
        $view = $module->view['show'] ?? 'camunda::process.show';

        try {
            $processInstance = (new ProcessInstanceHistory($processInstanceId))->fetch();
            $tasks = $processInstance->tasks($module->getTasks());

            $completedTasks = $this->workflow->completedTasks($processInstanceId, $module->getTasks());

            $forms = [];
            $otherTasks = [];
            foreach ($tasks as $task) {
                $taskConfig = $module->getTask($task->taskDefinitionKey);
                if (Arr::get($taskConfig, 'attributes.readonly', false)) {
                    $otherTasks[] = ['model' => $task, 'config' => $taskConfig];
                } else {
                    $forms[] = TaskForm::make($module, $task);
                }
            }

            return view(
                $view,
                compact('module', 'processInstance', 'tasks', 'completedTasks', 'forms', 'otherTasks')
            );
        } catch (ClientException $e) {
            abort($e->getCode(), $e->getMessage());
        }
    }

    public function edit(Module $module, $processInstanceId)
    {
        $this->authorize('edit', $module->getModel());

        try {
            $form = $this->workflow->editStartForm($module, $processInstanceId);

            return view('camunda::process.edit', compact('form'));
        } catch (ClientException $e) {
            abort($e->getCode(), $e->getMessage());
        }
    }

    public function update(Module $module, $processInstanceId, BasicRequest $request)
    {
        $this->authorize('edit', $module->getModel());

        try {
            $this->workflow->updateProcess($processInstanceId, $request->all());

            return redirect()
                ->route('camunda::process.show', [$module->id, $processInstanceId])
                ->withSuccess('OK');
        } catch (ClientException $e) {
            abort($e->getCode(), $e->getMessage());
        }
    }

    public function destroy(Module $module, $processInstanceId)
    {
        $this->authorize('delete', $module->getModel());

        try {
            $this->workflow->deleteProcess($processInstanceId);

            return redirect()
                ->back()
                ->withSuccess('Proses berhasil dihapus');
        } catch (ClientException $e) {
            abort($e->getCode(), $e->getMessage());
        }
    }

    public function report(Module $module, $format)
    {
        $path = request('path');

        if (! $path) {
            abort(404);
        }

        $path = '/reports/' . ltrim($path, '/');
        $download = request('download', false);
        $ids = json_decode(request('ids', '[]'));

        $path = sprintf("%s.%s", $path, $format);
        $jasper = app(Jasper::class);
        $filename = pathinfo($path)['basename'];
        $queryString = collect(request()->query())->except('path', 'download')->toArray();

        $query = $module->table->source(true);
        if ($query instanceof Builder) {
            if (! empty($ids)) {
                $query->whereIn('id', $ids);
            }
            $statement = str_replace(['?'], ['\'%s\''], $query->toSql());
            $query = vsprintf($statement, $query->getBindings());
        }

        $queryString['query'] = $query;

        $response = $jasper->get($path, ['query' => $queryString]);

        $response = response()
            ->make($response)
            ->header('Content-Type', $this->headers[$format] ?? 'text/plain');

        // Paksa untuk download file (bukan stream)
        if ($download) {
            $response->header('Content-Disposition', "attachment; filename={$filename}");
        }

        return $response;
    }
}
