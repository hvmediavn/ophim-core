<?php

namespace Ophim\Core\Controllers\Admin;

use Ophim\Core\Requests\MovieRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Ophim\Core\Models\Actor;
use Ophim\Core\Models\Director;
use Ophim\Core\Models\Movie;
use Ophim\Core\Models\Region;
use Ophim\Core\Models\Studio;
use Ophim\Core\Models\Category;
use Ophim\Core\Models\Tag;

/**
 * Class MovieCrudController
 * @package Ophim\Core\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MovieCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation {
        store as backpackStore;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation {
        update as backpackUpdate;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation {
        destroy as traitDestroy;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    use \Ophim\Core\Traits\Operations\BulkDeleteOperation {
        bulkDelete as traitBulkDelete;
    }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\Ophim\Core\Models\Movie::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/movie');
        CRUD::setEntityNameStrings('movie', 'movies');
        CRUD::setCreateView('ophim::movies.create',);
        CRUD::setUpdateView('ophim::movies.edit',);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        $this->authorize('browse', Movie::class);

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number','tab'=>'Th??ng tin phim']);
         */

        $this->crud->addFilter([
            'name'  => 'status',
            'type'  => 'select2',
            'label' => 'T??nh tr???ng'
        ], function () {
            return [
                'trailer' => 'S???p chi???u',
                'ongoing' => '??ang chi???u',
                'completed' => 'Ho??n th??nh'
            ];
        }, function ($val) {
            $this->crud->addClause('where', 'status', $val);
        });

        $this->crud->addFilter([
            'name'  => 'type',
            'type'  => 'select2',
            'label' => '?????nh d???ng'
        ], function () {
            return [
                'single' => 'Phim l???',
                'series' => 'Phim b???'
            ];
        }, function ($val) {
            $this->crud->addClause('where', 'type', $val);
        });

        $this->crud->addFilter([
            'name'  => 'category_id',
            'type'  => 'select2',
            'label' => 'Th??? lo???i'
        ], function () {
            return Category::all()->pluck('name', 'id')->toArray();
        }, function ($value) { // if the filter is active
            $this->crud->query = $this->crud->query->whereHas('categories', function ($query) use ($value) {
                $query->where('id', $value);
            });
        });

        $this->crud->addFilter([
            'name'  => 'region_id',
            'type'  => 'select2',
            'label' => 'Qu???c gia'
        ], function () {
            return Region::all()->pluck('name', 'id')->toArray();
        }, function ($value) { // if the filter is active
            $this->crud->query = $this->crud->query->whereHas('regions', function ($query) use ($value) {
                $query->where('id', $value);
            });
        });

        $this->crud->addFilter([
            'name'  => 'other',
            'type'  => 'select2',
            'label' => 'Th??ng tin'
        ], function () {
            return [
                'thumb_url-' => 'Thi???u ???nh thumb',
                'poster_url-' => 'Thi???u ???nh poster',
                'trailer_url-' => 'Thi???u trailer',
                'language-vietsub' => 'Vietsub',
                'language-thuy???t minh' => 'Thuy???t minh',
                'language-l???ng ti???ng' => 'L???ng ti???ng',
            ];
        }, function ($values) {
            $value = explode("-", $values);
            $field = $value[0];
            $val = $value[1];
            if($field === 'language') {
                $this->crud->query->where($field, 'like', '%' . $val . '%');
            } else {
                $this->crud->query->where($field, '')->orWhere($field, NULL);
            }
        });

        $this->crud->addFilter(
            [
                'type'  => 'simple',
                'name'  => 'is_recommended',
                'label' => '????? c???'
            ],
            false,
            function () {
                $this->crud->addClause('where', 'is_recommended', true);
            }
        );

        $this->crud->addFilter(
            [
                'type'  => 'simple',
                'name'  => 'is_shown_in_theater',
                'label' => 'Chi???u r???p'
            ],
            false,
            function () {
                $this->crud->addClause('where', 'is_shown_in_theater', true);
            }
        );

        CRUD::addButtonFromModelFunction('line', 'open_view', 'openView', 'beginning');

        CRUD::addColumn([
            'name' => 'name',
            'origin_name' => 'origin_name',
            'publish_year' => 'publish_year',
            'status' => 'status',
            'movie_type' => 'type',
            'episode_current' => 'episode_current',
            'label' => 'Th??ng tin',
            'type' => 'view',
            'view' => 'ophim::movies.columns.column_movie_info',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->where('name', 'like', '%' . $searchTerm . '%')->orWhere('origin_name', 'like', '%' . $searchTerm . '%');
            }
        ]);

        CRUD::addColumn([
            'name' => 'thumb_url', 'label' => '???nh thumb', 'type' => 'image',
            'height' => '100px',
            'width'  => '68px',
        ]);
        CRUD::addColumn(['name' => 'categories', 'label' => 'Th??? lo???i', 'type' => 'relationship',]);
        CRUD::addColumn(['name' => 'regions', 'label' => 'Khu v???c', 'type' => 'relationship',]);
        CRUD::addColumn(['name' => 'updated_at', 'label' => 'C???p nh???t l??c', 'type' => 'datetime', 'format' => 'DD/MM/YYYY HH:mm:ss']);
        // CRUD::addColumn(['name' => 'user_name', 'label' => 'C???p nh???t b???i', 'type' => 'text',]);
        CRUD::addColumn(['name' => 'view_total', 'label' => 'L?????t xem', 'type' => 'number',]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        $this->authorize('create', Movie::class);

        CRUD::setValidation(MovieRequest::class);

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number']));
         */

        CRUD::addField(['name' => 'name', 'label' => 'T??n phim', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-6'
        ], 'attributes' => ['placeholder' => 'T??n'], 'tab' => 'Th??ng tin phim']);
        CRUD::addField(['name' => 'origin_name', 'label' => 'T??n g???c', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-6'
        ], 'tab' => 'Th??ng tin phim']);
        CRUD::addField(['name' => 'slug', 'label' => '???????ng d???n t??nh', 'type' => 'text', 'tab' => 'Th??ng tin phim']);
        CRUD::addField([
            'name' => 'thumb_url', 'label' => '???nh Thumb', 'type' => 'ckfinder', 'preview' => ['width' => 'auto', 'height' => '340px'], 'tab' => 'Th??ng tin phim'
        ]);
        CRUD::addField(['name' => 'poster_url', 'label' => '???nh Poster', 'type' => 'ckfinder', 'preview' => ['width' => 'auto', 'height' => '340px'], 'tab' => 'Th??ng tin phim']);

        CRUD::addField(['name' => 'content', 'label' => 'N???i dung', 'type' => 'summernote', 'tab' => 'Th??ng tin phim']);
        CRUD::addField(['name' => 'notify', 'label' => 'Th??ng b??o / ghi ch??', 'type' => 'text', 'attributes' => ['placeholder' => 'Tu???n n??y ho??n chi???u'], 'tab' => 'Th??ng tin phim']);

        CRUD::addField(['name' => 'showtimes', 'label' => 'L???ch chi???u phim', 'type' => 'text', 'attributes' => ['placeholder' => '21h t???i h??ng ng??y'], 'tab' => 'Th??ng tin phim']);
        CRUD::addField(['name' => 'trailer_url', 'label' => 'Trailer Youtube URL', 'type' => 'text', 'tab' => 'Th??ng tin phim']);

        CRUD::addField(['name' => 'episode_time', 'label' => 'Th???i l?????ng t???p phim', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'attributes' => ['placeholder' => '45 ph??t'], 'tab' => 'Th??ng tin phim']);
        CRUD::addField(['name' => 'episode_current', 'label' => 'T???p phim hi???n t???i', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'attributes' => ['placeholder' => '5'], 'tab' => 'Th??ng tin phim']);

        CRUD::addField(['name' => 'episode_total', 'label' => 'T???ng s??? t???p phim', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'attributes' => ['placeholder' => '12'], 'tab' => 'Th??ng tin phim']);

        CRUD::addField(['name' => 'language', 'label' => 'Ng??n ng???', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'attributes' => ['placeholder' => 'Ti???ng Vi???t'], 'tab' => 'Th??ng tin phim']);

        CRUD::addField(['name' => 'quality', 'label' => 'Ch???t l?????ng', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'tab' => 'Th??ng tin phim']);

        CRUD::addField(['name' => 'publish_year', 'label' => 'N??m xu???t b???n', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'tab' => 'Th??ng tin phim']);

        CRUD::addField(['name' => 'type', 'label' => '?????nh d???ng', 'type' => 'radio', 'options' => ['single' => 'Phim l???', 'series' => 'Phim b???'], 'tab' => 'Ph??n lo???i']);
        CRUD::addField(['name' => 'status', 'label' => 'T??nh tr???ng', 'type' => 'radio', 'options' => ['trailer' => 'S???p chi???u', 'ongoing' => '??ang chi???u', 'completed' => 'Ho??n th??nh'], 'tab' => 'Ph??n lo???i']);
        CRUD::addField(['name' => 'categories', 'label' => 'Th??? lo???i', 'type' => 'checklist', 'tab' => 'Ph??n lo???i']);
        CRUD::addField(['name' => 'regions', 'label' => 'Khu v???c', 'type' => 'checklist', 'tab' => 'Ph??n lo???i']);
        CRUD::addField(['name' => 'directors', 'label' => '?????o di???n', 'type' => 'select2_relationship_tags', 'tab' => 'Ph??n lo???i']);
        CRUD::addField(['name' => 'actors', 'label' => 'Di???n vi??n',  'type' => 'select2_relationship_tags', 'tab' => 'Ph??n lo???i']);
        CRUD::addField(['name' => 'tags', 'label' => 'Tags',  'type' => 'select2_relationship_tags', 'tab' => 'Ph??n lo???i']);
        CRUD::addField(['name' => 'studios', 'label' => 'Studios',  'type' => 'select2_relationship_tags', 'tab' => 'Ph??n lo???i']);

        CRUD::addField([
            'name' => 'episodes',
            'type' => 'view',
            'view' => 'ophim::movies.inc.episode',
            'tab' => 'Danh s??ch t???p phim'
        ],);

        CRUD::addField(['name' => 'update_handler', 'label' => 'Tr??nh c???p nh???t', 'type' => 'select_from_array', 'options' => collect(config('ophim.updaters', []))->pluck('name', 'handler')->toArray(), 'tab' => 'C???p nh???t']);
        CRUD::addField(['name' => 'update_identity', 'label' => 'ID c???p nh???t', 'type' => 'text', 'tab' => 'C???p nh???t']);

        CRUD::addField(['name' => 'is_shown_in_theater', 'label' => 'Phim chi???u r???p', 'type' => 'boolean', 'tab' => 'Kh??c']);
        CRUD::addField(['name' => 'is_copyright', 'label' => 'C?? b???n quy???n phim', 'type' => 'boolean', 'tab' => 'Kh??c']);
        CRUD::addField(['name' => 'is_sensitive_content', 'label' => 'C???nh b??o n???i dung ng?????i l???n', 'type' => 'boolean', 'tab' => 'Kh??c']);
        CRUD::addField(['name' => 'is_recommended', 'label' => '????? c???', 'type' => 'boolean', 'tab' => 'Kh??c']);
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->authorize('update', $this->crud->getEntryWithLocale($this->crud->getCurrentEntryId()));

        $this->setupCreateOperation();
        CRUD::addField(['name' => 'timestamps', 'label' => 'C???p nh???t th???i gian', 'type' => 'checkbox', 'tab' => 'C???p nh???t']);
    }

    public function store(Request $request)
    {
        $this->getTaxonamies($request);

        return $this->backpackStore();
    }

    public function update(Request $request)
    {
        $this->getTaxonamies($request);

        return $this->backpackUpdate();
    }

    protected function getTaxonamies(Request $request)
    {
        $actors = request('actors', []);
        $directors = request('directors', []);
        $tags = request('tags', []);
        $studios = request('studios', []);

        $actor_ids = [];
        foreach ($actors as $actor) {
            $actor_ids[] = Actor::firstOrCreate([
                'name_md5' => md5($actor)
            ], [
                'name' => $actor
            ])->id;
        }

        $director_ids = [];
        foreach ($directors as $director) {
            $director_ids[] = Director::firstOrCreate([
                'name_md5' => md5($director)
            ], [
                'name' => $director
            ])->id;
        }

        $tag_ids = [];
        foreach ($tags as $tag) {
            $tag_ids[] = Tag::firstOrCreate([
                'name_md5' => md5($tag)
            ], [
                'name' => $tag
            ])->id;
        }

        $studio_ids = [];
        foreach ($studios as $studio) {
            $studio_ids[] = Studio::firstOrCreate([
                'name_md5' => md5($studio)
            ], [
                'name' => $studio
            ])->id;
        }

        $request['actors'] = $actor_ids;
        $request['directors'] = $director_ids;
        $request['tags'] = $tag_ids;
        $request['studios'] = $studio_ids;
    }

    // protected function setupDeleteOperation()
    // {
    //     $this->authorize('delete', $this->crud->getEntryWithLocale($this->crud->getCurrentEntryId()));
    // }

    public function deleteImage($movie)
    {
        // Delete images
        if ($movie->thumb_url && !filter_var($movie->thumb_url, FILTER_VALIDATE_URL) && file_exists(public_path($movie->thumb_url))) {
            unlink(public_path($movie->thumb_url));
        }
        if ($movie->poster_url && !filter_var($movie->poster_url, FILTER_VALIDATE_URL) && file_exists(public_path($movie->poster_url))) {
            unlink(public_path($movie->poster_url));
        }
        return true;
    }

    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');
        $movie = Movie::find($id);

        $this->deleteImage($movie);

        // get entry ID from Request (makes sure its the last ID for nested resources)
        $id = $this->crud->getCurrentEntryId() ?? $id;

        $res = $this->crud->delete($id);
        if ($res) {
        }
        return $res;
    }

    public function bulkDelete()
    {
        $this->crud->hasAccessOrFail('bulkDelete');
        $entries = request()->input('entries', []);
        $deletedEntries = [];

        foreach ($entries as $key => $id) {
            if ($entry = $this->crud->model->find($id)) {
                $this->deleteImage($entry);
                $deletedEntries[] = $entry->delete();
            }
        }

        return $deletedEntries;
    }
}
