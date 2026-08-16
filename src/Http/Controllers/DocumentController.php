<?php

namespace VanDmade\Blocksmith\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Http\Requests\DocumentRequest;
use VanDmade\Blocksmith\Http\Requests\TableRequest;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Services\DocumentService;
use VanDmade\Blocksmith\Services\HasherService;
use Exception;

class DocumentController extends BlocksmithController
{

    public function __construct(
        protected DocumentService $documentService,
    ) {
    }

    public function get(Document $document): JsonResponse
    {
        try {
            return $this->success([
                'document' => $document->load('currentRevision'),
            ]);
        } catch (Exception $error) {
            BlocksmithLog::dispatch(
                'error',
                $error->getMessage(),
                ['exception' => get_class($error)]
            );
            return $this->error($error->getMessage(), 500);
        }
    }

    public function data(TableRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $documents = $this->documentService
                ->search($data)
                ->paginate($data['per_page'], ['*'], 'page', $data['page']);
            return $this->success([
                'total' => $documents->total(),
                'per_page' => $documents->perPage(),
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'data' => $documents->items(),
            ]);
        } catch (Exception $error) {
            BlocksmithLog::dispatch(
                'error',
                $error->getMessage(),
                ['exception' => get_class($error)]
            );
            return $this->error($error->getMessage(), 500);
        }
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $file = $request->file('file');
            $document = $this->documentService->create(
                [
                    'name' => $data['name'] ?? $file->getClientOriginalName(),
                    'description' => $data['description'] ?? null,
                    'keywords' => $data['keywords'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                ],
                $this->hashUploadedFile($file),
                $this->pivotAttributesFor($file)
            );
            return $this->success([
                'message' => __('blocksmith::document.messages.created'),
                'document' => $document->load('currentRevision'),
            ], 201);
        } catch (Exception $error) {
            BlocksmithLog::dispatch(
                'error',
                $error->getMessage(),
                ['exception' => get_class($error)]
            );
            return $this->error($error->getMessage(), 500);
        }
    }

    public function update(DocumentRequest $request, Document $document): JsonResponse
    {
        try {
            $data = $request->validated();
            $file = $request->file('file');
            if ($file) {
                // New revision to upload
                $document = $this->documentService->revise(
                    $document,
                    $this->hashUploadedFile($file),
                    $this->pivotAttributesFor($file)
                );
                $message = __('blocksmith::document.messages.revised');
            } else {
                // Just updating the document's own fields
                $document = $this->documentService->update($document, [
                    'name' => $data['name'] ?? $document->name,
                    'description' => $data['description'] ?? $document->description,
                    'keywords' => $data['keywords'] ?? $document->keywords,
                    'metadata' => $data['metadata'] ?? $document->metadata,
                ]);
                $message = __('blocksmith::document.messages.updated');
            }
            return $this->success([
                'message' => $message,
                'document' => $document->load('currentRevision'),
            ]);
        } catch (Exception $error) {
            BlocksmithLog::dispatch(
                'error',
                $error->getMessage(),
                ['exception' => get_class($error)]
            );
            return $this->error($error->getMessage(), 500);
        }
    }

    public function destroy(Document $document): JsonResponse
    {
        try {
            $this->documentService->delete($document);
            return $this->success([
                'message' => __('blocksmith::document.messages.deleted'),
            ], 204);
        } catch (Exception $error) {
            BlocksmithLog::dispatch(
                'error',
                $error->getMessage(),
                ['exception' => get_class($error)]
            );
            return $this->error($error->getMessage(), 500);
        }
    }

    public function list(): JsonResponse
    {
        try {
            return $this->success([
                'list' => $this->documentService->search([
                    'select' => ['id as value', 'name as label'],
                ])->get(),
            ]);
        } catch (Exception $error) {
            BlocksmithLog::dispatch(
                'error',
                $error->getMessage(),
                ['exception' => get_class($error)]
            );
            return $this->error($error->getMessage(), 500);
        }
    }

    private function hashUploadedFile(UploadedFile $file): string
    {
        $stream = fopen($file->getRealPath(), 'r');
        $hash = app(HasherService::class)->hashContent($stream);
        fclose($stream);
        return $hash;
    }

    private function pivotAttributesFor(UploadedFile $file): array
    {
        $disk = config('blocksmith.disk', 'local');
        $path = $file->store('documents', $disk);
        return [
            'disk' => $disk,
            'path' => $path,
            'extension' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

}
