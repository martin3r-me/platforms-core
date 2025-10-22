<?php

namespace Platform\Core\Tools;

class CoreDataReadTool
{
    public function __construct(
        private DataReadTool $dataReadTool,
        private ToolBroker $toolBroker
    ) {}

    public function handle(array $arguments): array
    {
        $entity = $arguments['entity'] ?? null;
        $operation = $arguments['operation'] ?? null;

        if (!$entity || !$operation) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'entity and operation are required'
                ]
            ];
        }

        // Validate operation
        $validOperations = ['describe', 'list', 'get', 'search'];
        if (!in_array($operation, $validOperations)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => "Invalid operation. Must be one of: " . implode(', ', $validOperations)
                ]
            ];
        }

        // Route to appropriate method
        return match($operation) {
            'describe' => $this->dataReadTool->describe($entity),
            'list' => $this->dataReadTool->list($entity, $arguments),
            'get' => $this->dataReadTool->get($entity, (int)($arguments['id'] ?? 0)),
            'search' => $this->dataReadTool->search($entity, $arguments['query'] ?? '', $arguments),
            default => [
                'ok' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Unknown operation'
                ]
            ]
        };
    }
}
