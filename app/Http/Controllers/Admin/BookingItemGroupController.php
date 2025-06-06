<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingItemGroupRequest;
use App\Http\Resources\BookingItem\BookingItemGroupListResource;
use App\Http\Resources\BookingItemGroupResource;
use App\Models\Booking;
use App\Models\BookingItemGroup;
use App\Services\API\BookingItemGroupService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingItemGroupController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $request->validate((['product_type' => 'required|in:attraction,hotel,private_van_tour']));

        try {
            $main_query = BookingItemGroup::query()
                ->has('bookingItems')
                ->with([
                    'booking',
                    'bookingItems',
                ])
                ->where('product_type', (new BookingItemGroupService)->getModelBy($request->product_type))
                ->when($request->crm_id, function ($query) use ($request) {
                    $query->whereHas('booking', function ($q) use ($request) {
                        $q->where('crm_id', $request->crm_id);
                    });
                })
                ->when($request->product_name, function ($query) use ($request) {
                    $query->whereIn('id', function ($q) use ($request) {
                        $q->select('group_id')
                            ->from('booking_items')
                            ->whereIn('product_id', function ($subQuery) use ($request) {
                                if ($request->product_type == 'attraction') {
                                    $subQuery->select('id')
                                        ->from('attractions')
                                        ->where('name', 'like', '%' . $request->product_name . '%');
                                } elseif ($request->product_type == 'hotel') {
                                    $subQuery->select('id')
                                        ->from('hotels')
                                        ->where('name', 'like', '%' . $request->product_name . '%');
                                } elseif ($request->product_type == 'private_van_tour') {
                                    $subQuery->select('id')
                                        ->from('private_van_tours')
                                        ->where('name', 'like', '%' . $request->product_name . '%');
                                }
                            });
                    });
                })
                ->when($request->invoice_status, function ($query) use ($request) {
                    if ($request->invoice_status == 'not_receive') {
                        $query->whereIn('id', function ($q) {
                            $q->select('group_id')
                                ->from('booking_items')
                                ->whereNull('booking_status')
                                ->orWhere('booking_status', '')
                                ->orWhere('booking_status', 'not_receive');
                        });
                    } else {
                        $query->whereIn('id', function ($q) use ($request) {
                            $q->select('group_id')
                                ->from('booking_items')
                                ->where('booking_status', $request->invoice_status);
                        });
                    }
                })
                ->when($request->expense_item_status, function ($query) use ($request) {
                    $query->whereIn('id', function ($q) use ($request) {
                        $q->select('group_id')
                            ->from('booking_items')
                            ->where('payment_status', $request->expense_item_status);
                    });
                })
                ->when($request->customer_name, function ($query) use ($request) {
                    $query->whereHas('booking.customer', function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->customer_name . '%');
                    });
                })
                ->when($request->user_id, function ($query) use ($request) {
                    $query->whereHas('booking', function ($q) use ($request) {
                        $q->where('created_by', $request->user_id)
                            ->orWhere('past_user_id', $request->user_id);
                    });
                })
                ->when($request->payment_status, function ($query) use ($request) {
                    $query->whereHas('booking', function ($q) use ($request) {
                        $q->where('payment_status', $request->payment_status);
                    });
                });

            if ($request->booking_daterange && $request->product_type === 'private_van_tour') {
                $dates = explode(',', $request->booking_daterange);
                $dates = array_map('trim', $dates);

                if (count($dates) == 1 || (count($dates) == 2 && $dates[0] == $dates[1])) {
                    $exactDate = $dates[0];

                    $main_query->whereHas('bookingItems', function ($query) use ($exactDate) {
                        $query->where('service_date', $exactDate);
                    });
                } else {
                    $main_query->whereHas('bookingItems', function ($query) use ($dates) {
                        $query->whereBetween('service_date', $dates);
                    });
                }
            } elseif ($request->booking_daterange) {
                $dates = explode(',', $request->booking_daterange);
                $dates = array_map('trim', $dates);

                $main_query->whereHas('bookingItems', function ($query) use ($dates) {
                    $query->whereBetween('service_date', $dates);
                });
            }

            if (!in_array(Auth::user()->role, ['super_admin', 'reservation', 'auditor'])) {
                $main_query->whereHas('booking', function ($query) {
                    $query->where('created_by', Auth::id())
                        ->orWhere('past_user_id', Auth::id());
                });
            }

            $groups = $main_query->latest()->paginate($request->get('per_page', 5));

            return $this->success(BookingItemGroupListResource::collection($groups)
                ->additional([
                    'meta' => [
                        'total_page' => (int)ceil($groups->total() / $groups->perPage()),
                    ],
                ])
                ->response()
                ->getData(), 'Group List');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage());
        }
    }

    public function update(Booking $booking, BookingItemGroup $booking_item_group, BookingItemGroupRequest $request)
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('booking_request_proof')) {
                $booking_request_proof_file = upload_file($request->booking_request_proof, 'booking_item_groups/');

                $data['booking_request_proof'] = $booking_request_proof_file['fileName'];
            }

            if ($request->hasFile('expense_mail_proof')) {
                $expense_mail_proof_file = upload_file($request->expense_mail_proof, 'booking_item_groups/');

                $data['expense_mail_proof'] = $expense_mail_proof_file['fileName'];
            }

            if ($request->hasFile('confirmation_image')) {
                $confirmation_image_file = upload_file($request->confirmation_image, 'booking_item_groups/');

                $data['confirmation_image'] = $confirmation_image_file['fileName'];
            }

            $booking_item_group->update($data);

            return $this->success(new BookingItemGroupResource($booking_item_group), 'Booking Item Group updated successfully');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function storePassports(BookingItemGroup $booking_item_group, Request $request)
    {
        $request->validate([
            'passports' => 'required|array',
            'passports.*.file' => 'required|mimes:jpg,jpeg,png,pdf|max:2048',
            'passports.*.name' => 'nullable|string|max:255',
            'passports.*.passport_no' => 'nullable|string|max:255',
            'passports.*.dob' => 'nullable|date_format:Y-m-d',
            'passports.*.expiry_date' => 'nullable|date_format:Y-m-d',
            'passports.*.place_of_issue' => 'nullable|string|max:255',
            'passports.*.country_of_issue' => 'nullable|string|max:255',
        ]);

        try {
            foreach ($request->passports as $passport) {
                $passport_file = upload_file($passport->file, 'booking_item_groups/');

                $booking_item_group->customerDocuments()->create([
                    'type' => 'passport',
                    'file' => $passport_file['file'] ?? null,
                    'file_name' => $passport_file['filePath'] ?? null,
                    'mime_type' => $passport_file['fileType'] ?? null,
                    'file_size' => $passport_file['fileSize'] ?? null,
                ]);
            }

            return $this->success(null, 'Passport uploaded successfully');
        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }

    public function updatePassports(BookingItemGroup $booking_item_group, string $passport_id, Request $request)
    {
        try {
            $passport = $booking_item_group->passports()->find($passport_id);

            if (!$passport) {
                return $this->error(null, 'Passport not found', 404);
            }

            if ($request->hasFile('file')) {
                $passport_file = upload_file($request->file, 'booking_item_groups/');
            }

            $passport->update([
                'type' => 'passport',
                'file' => $passport_file['file'] ?? $passport->file,
                'file_name' => $passport_file['filePath'] ?? null,
                'mime_type' => $passport_file['fileType'] ?? null,
                'file_size' => $passport_file['fileSize'] ?? null,
            ]);

        } catch (Exception $e) {
            return $this->error(null, $e->getMessage(), 500);
        }
    }
}
