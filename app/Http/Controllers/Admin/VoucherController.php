<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationData;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Application|Factory|View
     */
    public function index()
    {
        return view('admin.vouchers.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('admin.vouchers.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'memo'       => 'sometimes|string|max:191',
            'code'       => 'required|string|alpha_dash|max:36',
            'uses'       => 'required|numeric|max:2147483647',
            'credits'    => 'required|numeric|between:0,99999999',
            'expires_at' => 'nullable|date|after:1 hour',
        ]);

        Voucher::create($request->except('_token'));

        return redirect()->route('admin.vouchers.index')->with('success', 'voucher has been created!');
    }

    /**
     * Display the specified resource.
     *
     * @param Voucher $voucher
     * @return Response
     */
    public function show(Voucher $voucher)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Voucher $voucher
     * @return Response
     */
    public function edit(Voucher $voucher)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Voucher $voucher
     * @return Response
     */
    public function update(Request $request, Voucher $voucher)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Voucher $voucher
     * @return RedirectResponse
     */
    public function destroy(Voucher $voucher)
    {
        $voucher->delete();
        return redirect()->back()->with('success', 'voucher has been removed!');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function redeem(Request $request)
    {
        #general validations
        $request->validate([
            'code' => 'required|exists:vouchers,code'
        ]);

        #get voucher by code
        $voucher = Voucher::where('code' , '=' , $request->input('code'))->firstOrFail();

        #extra validations
        if ($voucher->getStatus() == 'USES_LIMIT_REACHED') throw ValidationException::withMessages([
            'code' => 'This voucher has reached the maximum amount of uses'
        ]);

        if ($voucher->getStatus() == 'EXPIRED') throw ValidationException::withMessages([
            'code' => 'This voucher has expired'
        ]);

        if (!$request->user()->vouchers()->where('id' , '=' , $voucher->id)->get()->isEmpty()) throw ValidationException::withMessages([
            'code' => 'You already redeemed this voucher code'
        ]);

        if ($request->user()->credits + $voucher->credits >= 99999999) throw ValidationException::withMessages([
            'code' => "You can't redeem a voucher with this many credits"
        ]);

        #redeem voucher
        $voucher->redeem($request->user());

        return response()->json([
            'success' => "{$voucher->credits} credits have been added to your balance!"
        ]);
    }

    public function dataTable()
    {
        $query = Voucher::query();

        return datatables($query)
            ->addColumn('actions', function (Voucher $voucher) {
                return '
                            <a data-content="Show" data-toggle="popover" data-trigger="hover" data-placement="top" href="' . route('admin.vouchers.show', $voucher->id) . '" class="btn btn-sm text-white btn-warning mr-1"><i class="fas fa-eye"></i></a>
                            <a data-content="Edit" data-toggle="popover" data-trigger="hover" data-placement="top" href="' . route('admin.vouchers.edit', $voucher->id) . '" class="btn btn-sm btn-info mr-1"><i class="fas fa-pen"></i></a>

                           <form class="d-inline" onsubmit="return submitResult();" method="post" action="' . route('admin.vouchers.destroy', $voucher->id) . '">
                            ' . csrf_field() . '
                            ' . method_field("DELETE") . '
                           <button data-content="Delete" data-toggle="popover" data-trigger="hover" data-placement="top" class="btn btn-sm btn-danger mr-1"><i class="fas fa-trash"></i></button>
                       </form>
                ';
            })
            ->addColumn('status', function (Voucher $voucher) {
                $color = 'success';
                if ($voucher->getStatus() != 'VALID') $color = 'danger';
                return '<span class="badge badge-' . $color . '">' . $voucher->getStatus() . '</span>';
            })
            ->editColumn('uses', function (Voucher $voucher) {
                $userCount = $voucher->users()->count();
                return "{$userCount} / {$voucher->uses}";
            })
            ->editColumn('credits', function (Voucher $voucher) {
                return number_format($voucher->credits, 2, '.', '');
            })
            ->editColumn('expires_at', function (Voucher $voucher) {
                if (!$voucher->expires_at) return "";
                return $voucher->expires_at ? $voucher->expires_at->diffForHumans() : '';
            })
            ->editColumn('code', function (Voucher $voucher) {
                return "<code>{$voucher->code}</code>";
            })
            ->rawColumns(['actions', 'code', 'status'])
            ->make();
    }

}