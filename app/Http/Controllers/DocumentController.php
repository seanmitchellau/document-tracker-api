<?php

namespace App\Http\Controllers;

use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->documents();

        match ($request->query('filter')) {
            'expiring_soon' => $query->expiringSoon(),
            'expired' => $query->expired()->notArchived(),
            default => null,
        };

        $sortField = $request->query('sort', 'expires_at');
        $sortDirection = 'asc';

        if (str_starts_with($sortField, '-')) {
            $sortField = substr($sortField, 1);
            $sortDirection = 'desc';
        }

        if (in_array($sortField, ['name', 'expires_at'])) {
            $query->orderBy($sortField, $sortDirection);
        }

        return DocumentResource::collection(
            resource: $query->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf'],
            'expires_at' => ['required', 'date'],
        ]);

        $path = $request->file('file')->store('documents');

        $document = $request
            ->user()
            ->documents()
            ->create([
                'name' => $validated['name'],
                'path' => $path,
                'expires_at' => $validated['expires_at'],
            ]);

        return DocumentResource::make($document);
    }

    public function show(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        return DocumentResource::make($document);
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $document->update($validated);

        return DocumentResource::make($document);
    }

    public function archive(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        abort_unless($document->expires_at && $document->expires_at->isPast(), 403, 'Only expired documents can be archived.');

        $document->update(['archived_at' => now()]);

        return DocumentResource::make($document);
    }
}
