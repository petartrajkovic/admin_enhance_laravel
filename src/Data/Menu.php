<?php
/**
 * Copyright (c) 2018. Mallto.Co.Ltd.<mall-to.com> All rights reserved.
 */

namespace Mallto\Admin\Data;

use Encore\Admin\Traits\AdminBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mallto\Admin\AdminUtils;
use Mallto\Admin\CacheConstants;
use Mallto\Admin\CacheUtils;
use Mallto\Admin\Data\Traits\PermissionHelp;
use Mallto\Admin\Traits\ModelTree;

/**
 * Class Menu.
 *
 * @property int $id
 *
 * @method where($parent_id, $id)
 */
class Menu extends Model
{

    use PermissionHelp, AdminBuilder, ModelTree {
        ModelTree::boot as treeBoot;
    }

//    protected $fillable = ['parent_id', 'order', 'title', 'icon', 'uri'];
    protected $guarded = [];

    protected $fillable = [];


    /**
     * Create a new Eloquent model instance.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $connection = config('admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('admin.database.menu_table'));

        parent::__construct($attributes);
    }


    public function getTitleAttribute($value)
    {
        $isOwner = AdminUtils::isOwner();

        if ($isOwner && $this->sub_title) {
            return $value . "-" . $this->sub_title;
        } else {
            return $value;
        }
    }


    public function subjects()
    {
        return $this->belongsToMany(Subject::class, "admin_menu_subjects", "admin_menu_id", "subject_id");
    }


    /**
     * A Menu belongs to many roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $pivotTable = config('admin.database.role_menu_table');

        $relatedModel = config('admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'menu_id', 'role_id');
    }


    /**
     * ????????????????????????
     *
     * @return array
     */
    public function parentMenu()
    {
        if ( ! empty($this->path)) {
            $parentIds = explode(".", trim($this->path, "."));
            if ( ! empty($parentIds)) {
                return Menu::whereIn("id", $parentIds)
                    ->get()
                    ->toArray();
            }
        }

        return [];
    }


    public function parentMenu2()
    {
        $tempMenus = \DB::select("with recursive tab as (
                   select * from admin_menu where id = $this->parent_id
                   union all
                   select s.* from admin_menu as s inner join tab on tab.parent_id = s.id
                )
           select * from tab order by id");

        $menus = json_decode(json_encode($tempMenus), true);

        return $menus;
    }


    /**
     * @return array
     */
    public function allNodes(): array
    {
        $orderColumn = DB::getQueryGrammar()->wrap($this->orderColumn);
        $byOrder = $orderColumn . ' = 0,' . $orderColumn;
        if (config("admin.auto_menu")) {
            //????????????????????????,??????????????????
            //???????????????????????????,???????????????????????????
            //??????????????????,??????????????????;?????????????????????,??????????????????
            $adminUser = Auth::guard("admin")->user();
            if ($adminUser->isOwner()) {
                return static::orderByRaw($byOrder)->get()->toArray();
            } else {
                $result = Cache::get("menu_" . $adminUser->id);
                if ($result) {
                    return $result;
                }

                //???????????????????????????????????????
                $permissions = $adminUser->allPermissions();
                $userPermissions = $this->withSubPermissions($permissions);
                $userPermissionSlugs = array_pluck($userPermissions, "slug");

//                $tempPermissionSlugs = $userPermissionSlugs;
//
//                foreach ($userPermissionSlugs as $userPermissionSlug) {
//                    if (!str_contains($userPermissionSlug, ".")) {
//                        $tempPermissionSlugs[] = $userPermissionSlug.".index";
//                    }
//                }
//
//                $userPermissionSlugs = $tempPermissionSlugs;

                $menus = new Collection();
                //??????????????????????????????????????????
                $menus = $menus->merge(static::where("uri", "dashboard")->get());

                $menusQuery = static::whereIn("uri", $userPermissionSlugs);

                if ( ! $adminUser->isOwner()) {
                    $menusQuery = $menusQuery->where(function ($query) use ($adminUser) {
                        $query->orWhereDoesntHave("subjects", function ($query) {

                        })->orWhereHas("subjects", function ($query) use ($adminUser) {
                            $query->where("id", $adminUser->subject_id);
                        });
                    });
                }

                //???????????????????????????????????????????????????????????????
                $menus = $menus->merge($menusQuery->get());

                $tempMenus = $menus->toArray();

                //????????????????????????????????????????????????,??????parent_id???0
                foreach ($menus as $item) {
                    $tempMenus = array_merge($tempMenus, $item->parentMenu());
                }

                //??????????????????
                $uniqueTempArray = [];
                $tempMenus = array_filter($tempMenus, function ($menu) use (&$uniqueTempArray) {
                    if ( ! in_array($menu["id"], $uniqueTempArray)) {
                        $uniqueTempArray[] = $menu["id"];

                        return true;
                    } else {
                        return false;
                    }
                });

                //??????
                $result = array_sort($tempMenus, $this->orderColumn);

                $cacheMenuKey = "menu_" . $adminUser->id;
                CacheUtils::putMenu($cacheMenuKey, $result);

                $cacheMenuKeys = Cache::get(CacheConstants::CACHE_MENU_KEYS, []);
                $cacheMenuKeys[] = $cacheMenuKey;

                CacheUtils::putMenuKeys($cacheMenuKeys);

                return $result;
            }
        } else {
            return static::with('roles')->orderByRaw($byOrder)->get()->toArray();
        }
    }


    /**
     * Detach models from the relationship.
     *
     * @return void
     */
    protected static function boot()
    {
        static::treeBoot();

        static::deleting(function ($model) {
            $model->roles()->detach();
        });
    }
}
