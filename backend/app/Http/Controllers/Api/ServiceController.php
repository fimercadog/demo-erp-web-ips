<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreProductImageRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Rules\ImageFile;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceController extends BaseCrudController
{
    protected string $model = Service::class;

    protected string $resource = ServiceResource::class;

    protected array $with = ['category'];

    protected array $searchable = ['name', 'description'];

    protected array $filterable = [
        'status' => 'status',
        'type' => 'type',
        'vertical' => 'vertical',
        'category_id' => 'category_id',
        'is_public' => 'is_public',
    ];

    /** Sube la imagen del servicio (catálogo público). */
    public function image(StoreProductImageRequest $request, string $id, AuditService $audit)
    {
        $companyId = $this->companyId($request);

        $service = Service::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $old = $service->getOriginal();
        $previous = $this->ownStoragePath($service->image_url, $companyId);

        $file = $request->file('image');
        $name = Str::random(40).'.'.ImageFile::extensionFor($file);
        $path = $file->storeAs('services/'.$companyId, $name, 'public');

        $service->forceFill(['image_url' => Storage::disk('public')->url($path)])->save();
        $audit->record('updated', $service, $request, $old);

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return new ServiceResource($service->load($this->with));
    }

    private function ownStoragePath(?string $imageUrl, int $companyId): ?string
    {
        if (! $imageUrl || ! Str::contains($imageUrl, '/storage/')) {
            return null;
        }

        $path = Str::afterLast($imageUrl, '/storage/');
        $prefix = 'services/'.$companyId.'/';

        return (str_starts_with($path, $prefix) && ! str_contains($path, '..')) ? $path : null;
    }
}
