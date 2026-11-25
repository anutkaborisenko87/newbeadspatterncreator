<?php

namespace App\Services;

use App\Contracts\Services\PageSectionsServiceInterface;
use App\Contracts\Services\PageServiceInterface;
use App\Contracts\Services\ProfileServiceInterface;
use App\DTO\AuthProfileDataDTO;
use App\DTO\ProfileDataDTO;
use App\Models\User;
use App\Models\CommentReport;
use App\Models\PatternReport;
use Illuminate\Support\Facades\Auth;

class ProfileService implements ProfileServiceInterface
{

    public function __construct(
        private PageServiceInterface $pageService,
        private PageSectionsServiceInterface $pageSectionsService,
    ) {}

    /**
     * @inheritDoc
     */
    public function getProfilePage(): array
    {
        $pageData = $this->pageService->getPageData('gallery')->toArray();
        $gallerySections = $pageData['data']['sections'];
        $pageData['data']['title'] = __('main.profile_title');
        $pageData["links"] = [
            "self" => "/my-profile",
            "slug" => "my-profile"
        ];
        $newSectionsArray = [];
        $newSectionsArray[] = $this->pageSectionsService->getProfileTitleSection();
        $newSectionsArray[] = $this->pageSectionsService->getProfileUserSection(Auth::user());
        $patternReports = PatternReport::where('reporter_id', Auth::id())->orWhere('author_id', Auth::id())
            ->with(['pattern' => fn ($query) => $query->publiclyShared()->select('id')])
            ->latest('id')->simplePaginate(5, ['id', 'pattern_id', 'title', 'topic', 'status', 'reporter_id'], 'pattern_reports_page')
            ->withQueryString()->fragment('pattern-reports')
            ->through(fn (PatternReport $report) => [
                'id' => $report->id, 'title' => $report->title ?: ($report->pattern_id ? '#'.$report->pattern_id : null),
                'patternUrl' => $report->pattern ? route('patterns.comments.show', encodeModelId($report->pattern->id), absolute: false) : null,
                'topic' => __('pattern_reports.topics.'.$report->topic), 'status' => __('reports.statuses.'.$report->status),
                'notice' => __('pattern_reports.'.($report->reporter_id == Auth::id() ? 'submitted' : 'received')),
            ]);
        if ($patternReports->isNotEmpty() || $patternReports->currentPage() > 1) {
            $newSectionsArray[] = ['slug' => 'pattern_reports', 'reports' => $patternReports, 'labels' => ['heading' => __('pattern_reports.heading')]];
        }
        $reports = CommentReport::where('reporter_id', Auth::id())->latest('updated_at')->orderByDesc('id')
            ->simplePaginate(5, ['id', 'reported_login', 'topic', 'status', 'message', 'decision'], 'reports_page')
            ->withQueryString()->fragment('my-reports');
        if ($reports->isNotEmpty() || $reports->currentPage() > 1) {
            $newSectionsArray[] = ['slug' => 'my_reports', 'reports' => $reports, 'labels' => __('reports')];
        }
        foreach ($gallerySections as $section) {
            foreach ($section['gallery_items'] as $itemKey=>$item) {
                $requestedSortBy = request()->query($itemKey . "_sort_by");
                $sortSelect = $section['sort_select'];

                $sortSelectOptions = array_map(function($item) use ($requestedSortBy, $itemKey) {
                    $item['selected'] = $item['id'] == $requestedSortBy && request()->has($itemKey . "_sort_by");
                    return $item;
                }, $sortSelect['sort_select_items']);
                $sortSelect['sort_select_items'] = $sortSelectOptions;

                $newSectionsArray[] = [
                    'slug' => $itemKey,
                    'sort_select' => $sortSelect,
                    'section_title' => __('main.profile_'.$itemKey . '_title' ),
                    'gallery_items' => $item,
                    ...($itemKey === 'liked' ? ['url' => route('profile.liked', absolute: false)] : []),
                    ...($itemKey === 'drafts' ? [
                        'labels' => __('main.profile_draft_cards'),
                        'url' => route('profile.drafts', absolute: false),
                    ] : []),
                ];
            }
        }
        $pageData['data']['sections'] = $newSectionsArray;
        return $pageData;
    }

    /**
     * @inheritDoc
     */
    public function getAuthUserData(): AuthProfileDataDTO
    {
        return AuthProfileDataDTO::fromUser(Auth::user());
    }

    /**
     * @inheritDoc
     */
    public function updateAuthUserData(array $userData): AuthProfileDataDTO
    {
        $updatedUser = User::findOrFail(Auth::id());
        $profileDataDTO = ProfileDataDTO::fromArray($userData);
        $updatedUser->update($profileDataDTO->toArray());
        return AuthProfileDataDTO::fromUser($updatedUser);
    }
}
