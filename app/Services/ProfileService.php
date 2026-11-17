<?php

namespace App\Services;

use App\Contracts\Services\PageSectionsServiceInterface;
use App\Contracts\Services\PageServiceInterface;
use App\Contracts\Services\ProfileServiceInterface;
use App\DTO\AuthProfileDataDTO;
use App\DTO\ProfileDataDTO;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ProfileService implements ProfileServiceInterface
{

    public function __construct(
        private PageServiceInterface $pageService,
        private PageSectionsServiceInterface $pageSectionsService,
    ) {}

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
                    'gallery_items' => $item
                ];
            }
        }
        $pageData['data']['sections'] = $newSectionsArray;
        return $pageData;
    }

    public function getAuthUserData(): AuthProfileDataDTO
    {
        return AuthProfileDataDTO::fromUser(Auth::user());
    }

    public function updateAuthUserData(array $userData): AuthProfileDataDTO
    {
        $updatedUser = User::findOrFail(Auth::id());
        $profileDataDTO = ProfileDataDTO::fromArray($userData);
        $updatedUser->update($profileDataDTO->toArray());
        return AuthProfileDataDTO::fromUser($updatedUser);
    }
}
