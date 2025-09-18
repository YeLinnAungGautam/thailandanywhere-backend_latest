<?php
namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class EntranceTicketDiscountController extends Controller
{
    use HttpResponses;

    public function index()
    {
        $setting = Setting::where('meta_key', Setting::ENTRANCE_TICKET_DISCOUNT)->first();

        return $this->success(
            $setting ? $this->format($setting) : null,
            'Entrance ticket discount retrieved'
        );
    }

    public function show($id = null)
    {
        return $this->index();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meta_value' => 'required|integer|min:0|max:100',
        ]);

        $setting = Setting::updateOrCreate(
            ['meta_key' => Setting::ENTRANCE_TICKET_DISCOUNT],
            ['meta_value' => (string) $validated['meta_value']]
        );

        return $this->success(
            $this->format($setting),
            'Entrance ticket discount saved',
            201
        );
    }

    public function update(Request $request, $id = null)
    {
        $validated = $request->validate([
            'meta_value' => 'required|integer|min:0|max:100',
        ]);

        $setting = Setting::updateOrCreate(
            ['meta_key' => Setting::ENTRANCE_TICKET_DISCOUNT],
            ['meta_value' => (string) $validated['meta_value']]
        );

        return $this->success(
            $this->format($setting),
            'Entrance ticket discount updated',
            200
        );
    }

    public function destroy($id = null)
    {
        $setting = Setting::where('meta_key', Setting::ENTRANCE_TICKET_DISCOUNT)->first();
        if ($setting) {
            $setting->delete();
        }

        return $this->success(
            null,
            'Entrance ticket discount deleted',
            200
        );
    }

    private function format(Setting $setting): array
    {
        return [
            'id' => $setting->id,
            'meta_key' => $setting->meta_key,
            'meta_value' => is_numeric($setting->meta_value) ? (int) $setting->meta_value : $setting->meta_value,
            'created_at' => optional($setting->created_at)->toDateTimeString(),
            'updated_at' => optional($setting->updated_at)->toDateTimeString(),
        ];
    }
}
