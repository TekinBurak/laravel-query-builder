<?php

namespace BarisBora\QueryBuilder;

use Illuminate\Database\Eloquent\Builder;

class QueryBuilderHandler extends Builder
{

    /**
     * @var \Illuminate\Http\Request
     */
    private $request;

    public function __construct( Builder $builder, $request )
    {

        parent::__construct( clone $builder->getQuery() );

        $this->request = $request;

        $this
            ->setModel( $builder->getModel() )
            ->setEagerLoads( $builder->getEagerLoads() );

        $builder->macro( 'getProtected', function ( Builder $builder, string $property ) {

            return $builder->{$property};

        } );

    }

    /**
     * @param      $property
     * @param bool $scope
     * @return $this
     */
    public function allowFilter( $property, bool $scope = false )
    {

        $filters = (array) $this->request->get( 'filter' );

        if ( isset( $filters[ $property ] ) ) {

            $value = $filters[ $property ];

            $relations = explode( '.', $property );

            $property = array_splice( $relations, count( $relations ) - 1 )[ 0 ];

            $this->cascadeDownRelations( $relations, $property, $scope, $value );

        }

        return $this;

    }

    /**
     * @param array $includes
     * @return $this
     */
    public function allowedIncludes( array $includes )
    {

        $includes = collect( $includes )->transform( function ( $include ) {

            $incs = [];

            $explode = array_reverse( explode( '.', $include ) );

            foreach ( $explode as $key => $item ) {
                $incs[] = implode( '.', array_reverse( array_slice( $explode, $key, null, true ) ) );
            }

            return $incs;

        } )->unique()->flatten()->values();

        $getIncludes = collect( explode( ',', $this->request->get( 'include' ) ) )->unique()->values();

        $this->with( $getIncludes->intersect( $includes )->toArray() );

        return $this;

    }

    public function allowedLoads( $request, $loads )
    {
        $loads = collect( $loads )->transform( function ( $include ) {

            $incs = [];

            $explode = array_reverse( explode( '.', $include ) );

            foreach ( $explode as $key => $item ) {
                $incs[] = implode( '.', array_reverse( array_slice( $explode, $key, null, true ) ) );
            }

            return $incs;

        } )->unique()->flatten()->values();

        $getIncludes = collect( explode( ',', $this->request->get( 'include' ) ) )->unique()->values();

        $this->load( $getIncludes->intersect( $loads )->toArray() );

        return $this;
    }

    public function allowedSorts( array $sorts )
    {

        $getSorts = collect( explode( ',', $this->request->get( 'sort' ) ) );

        foreach ( $getSorts as $sort ) {

            if ( substr( $sort, 0, 1 ) == '-' ) {

                $column = substr( $sort, 1 );
                $order = 'desc';

            } else {

                $column = $sort;
                $order = 'asc';

            }

            if ( ! in_array( $column, $sorts ) ) continue;

            $this->orderBy( $column, $order );

        }

        return $this;
    }

    public function fetch( $columns = [ '*' ], $pager = 25 )
    {
        if ( $this->request->paginate !== 'false' && (bool)$this->request->paginate !== false ) return parent::paginate( $pager, $columns, $pageName = 'page', $page = null ); // TODO: Change the autogenerated stub

        return parent::get( $columns );
    }

    /**
     * @param      $relations
     * @param      $property
     * @param      $scope
     * @param      $value
     * @param null $query
     * @return $this
     */
    private function cascadeDownRelations( $relations, $property, $scope, $value, $query = null )
    {

        if ( is_null( $query ) ) $query = $this;

        if ( count( $relations ) === 0 && $scope ) return $query->{$property}( $value );

        if ( count( $relations ) === 0 ) return $query->where( $property, $value );

        $relation = array_shift( $relations );

        $query->whereHas( $relation, function ( $query ) use ( $relation, $property, $scope, $value, $relations ) {

            return $this->cascadeDownRelations( $relations, $property, $scope, $value, $query );

        } );

    }
}
