<?php

namespace App\Http\Controllers\Panel;

use App\Models\Partner;
use App\Services\MediaException;
use App\Services\MediaStore;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PartnerController extends PanelController
{
    public function __construct(private MediaStore $store, private ActivityLogger $log) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Partner::class);
        $query = Partner::with('logo')->orderBy('position')->orderBy('name');
        if (! $request->boolean('arquivados')) {
            $query->whereNull('archived_at');
        }

        return view('panel.partners.index', ['partners' => $query->get(), 'showArchived' => $request->boolean('arquivados')]);
    }

    public function create()
    {
        $this->authorize('create', Partner::class);

        return view('panel.partners.form', ['partner' => new Partner(['is_public' => true])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Partner::class);
        $this->save($request, new Partner(['portfolio_id' => $this->portfolio()->id]));

        return redirect()->route('panel.partners.index')->with('status', 'Parceiro cadastrado.');
    }

    public function edit(Partner $partner)
    {
        $this->authorize('update', $partner);

        return view('panel.partners.form', ['partner' => $partner]);
    }

    public function update(Request $request, Partner $partner)
    {
        $this->authorize('update', $partner);
        $this->save($request, $partner);

        return redirect()->route('panel.partners.edit', $partner)->with('status', 'Parceiro atualizado.');
    }

    public function archive(Request $request, Partner $partner)
    {
        $this->authorize('update', $partner);
        $partner->archived_at = now();
        $partner->save();
        $this->log->log($request->user(), 'partner.archived', $partner, 'Arquivou o parceiro '.$partner->name);

        return redirect()->route('panel.partners.index')->with('status', 'Parceiro arquivado.');
    }

    public function unarchive(Request $request, Partner $partner)
    {
        $this->authorize('update', $partner);
        $partner->archived_at = null;
        $partner->save();

        return back()->with('status', 'Parceiro reativado.');
    }

    private function save(Request $request, Partner $partner): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'between:0,9999'],
            'logo' => ['nullable', 'file'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [], ['name' => 'nome', 'url' => 'site']);
        $partner->fill([
            'name' => $data['name'],
            'url' => $data['url'] ?? null,
            'description' => $data['description'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'position' => (int) ($data['position'] ?? 0),
        ]);
        if ($request->boolean('remove_logo')) {
            $partner->logo_media_id = null;
        }
        if ($request->hasFile('logo')) {
            try {
                $partner->logo_media_id = $this->store->storeUploadedImage($request->file('logo'), $this->portfolio(), $request->user())->id;
            } catch (MediaException $e) {
                throw ValidationException::withMessages(['logo' => $e->getMessage()]);
            }
        }
        $partner->save();
        $this->log->log($request->user(), 'partner.saved', $partner, 'Salvou o parceiro '.$partner->name);
    }
}
