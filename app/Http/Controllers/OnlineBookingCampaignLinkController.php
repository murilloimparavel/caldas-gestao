<?php

namespace App\Http\Controllers;

use App\Actions\OnlineBooking\CreateOnlineBookingCampaignLink;
use App\Http\Requests\Settings\OnlineBookingCampaignLinkRequest;
use App\Models\OnlineBookingCampaignLink;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

final class OnlineBookingCampaignLinkController extends Controller
{
    public function index(TenantContext $context): Response|JsonResponse
    {
        Gate::authorize('view', $context->unit);
        $links = OnlineBookingCampaignLink::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->with('site')->latest()->get();

        $payload = ['campaignLinks' => $links->map(fn (OnlineBookingCampaignLink $link): array => [...$link->toArray(), 'url' => $this->url($link)])->values()->all()];

        return request()->expectsJson() ? response()->json(['campaign_links' => $payload['campaignLinks']]) : Inertia::render('online-booking/campaign-links', $payload);
    }

    public function store(OnlineBookingCampaignLinkRequest $request, TenantContext $context, CreateOnlineBookingCampaignLink $create): JsonResponse
    {
        $link = $create->handle($request->user(), $context, $request->validated());

        return response()->json(['campaign_link' => [...$link->load('site')->toArray(), 'url' => $this->url($link)]], 201);
    }

    private function url(OnlineBookingCampaignLink $link): string
    {
        return URL::route('public_booking.slug', ['public_slug' => $link->site->public_slug]).'?'.http_build_query(array_filter([
            'utm_source' => $link->utm_source,
            'utm_medium' => $link->utm_medium,
            'utm_campaign' => $link->utm_campaign,
            'utm_term' => $link->utm_term,
            'utm_content' => $link->utm_content,
        ]));
    }
}
