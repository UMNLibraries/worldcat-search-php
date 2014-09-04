<?php

require_once 'WorldCatSearch/Request.php';
require_once 'ArgValidator.php';

class WorldCatSearch_Request_Iterator_OCLCIdList implements Iterator
{
    // TODO: Most, if not all, of these set_ methods should probably be 'protected'.

    // From WorldCatSearch_Request:
    protected $api = 'sru';
    function set_api($api)
    {
        ArgValidator::validate(
            $api,
            array('is' => 'string', 'regex' => '/^(sru|opensearch)$/i')
        );
        $this->api = $api;
    }

    // Queries will be generated based on this:
    protected $oclc_id_list;
    function set_oclc_id_list(array $oclc_id_list)
    {
        // TODO: Add array[int] validation?
        $this->oclc_id_list = $oclc_id_list;
    }

    protected $wskey;
    function set_wskey($wskey)
    {
        ArgValidator::validate( $wskey, array('is' => 'string') );
        $this->wskey = $wskey;
    }

    protected $servicelevel = 'full';
    function set_servicelevel($servicelevel)
    {
        ArgValidator::validate(
            $servicelevel,
            array('is' => 'string', 'regex' => '/^(default|full)$/i')
        );
        $this->servicelevel = $servicelevel;
    }

    protected $schema = 'marcxml';
    function set_schema($schema)
    {
        ArgValidator::validate(
            $schema,
            array('is' => 'string', 'regex' => '/^(dc|marcxml)$/i')
        );
        $this->schema = $schema;
    }

    // This class should generate these automatically...
    protected $start_record = 1;
    function set_start_record($start_record)
    {
        ArgValidator::validate( $start_record, array('is' => 'int') );
        $this->start_record = $start_record;
    }

    //...based on these (from WorldCatSearch_Client):

    // TODO: WorldCat seems to have a maximum of 50 records per download. Investigate...
    //self::has('maximumRecords', array('is' => 'protected', 'default' => 50,)); // 50 is max-per-request allowed by WorldCat.
    protected $records_per_download = 50;
    function set_records_per_download($records_per_download)
    {
        ArgValidator::validate( $records_per_download, array('is' => 'int') );
        $this->records_per_download = $records_per_download;
    }

    // This will be calulated in __construct():
    protected $max_possible_desired_record = 0;
    protected function set_max_possible_desired_record($max_possible_desired_record)
    {
        ArgValidator::validate( $max_possible_desired_record, array('is' => 'int') );
        $this->max_possible_desired_record = $max_possible_desired_record;
    }

    protected $user_agent;
    function set_user_agent($user_agent)
    {
        /* TODO: Implement a UserAgent base class to make this validation possible.
        ArgValidator::validate(
            func_get_args(), array(array('instanceof' => UserAgent))
        );
        */
        $this->user_agent = $user_agent;
    }

    protected $current_iteration = 0;
    protected function set_current_iteration($current_iteration)
    {
        ArgValidator::validate( $current_iteration, array('is' => 'int') );
        $this->current_iteration = $current_iteration;
    }

    // Accessors:
    public function __call($function, $args)
    {
        // Since we're handling only accessors here, the function name should
        // be the same as the property name:
        $property = $function;
        $class = get_class($this);
        $ref_class = new ReflectionClass( $class );
        if (!$ref_class->hasProperty($property)) {
            throw new Exception("Method '$function' does not exist in class '$class'.");
        }
        return $this->$property;
    }

    function __construct(Array $args)
    {
        $validated_args = ArgValidator::validate(
            $args,
            array('wskey' => array('required' => true), 'oclc_id_list' => array('required' => true),)
        );

        foreach ($validated_args as $property => $value) {
            $mutator = 'set_' . $property;
            $this->$mutator( $value );
        }

        if ($this->user_agent() === null) {
            require_once 'WorldCatSearch/UserAgent.php';
            $this->set_user_agent( new WorldCatSearch_UserAgent() );
        }

        $this->set_max_possible_desired_record( count($this->oclc_id_list()) );
    }

    // Iterator interface implementation:

    public function rewind()
    {
        // Make sure the iterator is never valid if there are no records:
        $this->valid = count($this->oclc_id_list()) > 0 ? true : false;

        // TODO: Add this to the "parent" class!!!
        $this->set_current_iteration(0);

        $this->bootstrapped = false;
    }

    function current()
    {
        // Given Iterator's goofy method call order,
        // in which it calls current() before next(),
        // we have to bootstrap current so that it 
        // will have a value for the first loop iteration.
        if (!$this->bootstrapped) {
            $this->bootstrapped = true;
            $this->next_request();

        }
        return $this->current;
    }

    protected function set_key( $key )
    {
        $this->current_key = $key;
    }

    function key()
    {
        return $this->current_key;
    }

    function next()
    {
        $this->next_request();
    }

    function next_request()
    {
        $records_per_download = $this->records_per_download();
        $start_record = $this->start_record() +
            ($records_per_download * $this->current_iteration());

        $max_possible_desired_record = $this->max_possible_desired_record();

        if ($start_record > $max_possible_desired_record) {
            $this->valid = false;
            unset( $this->current );
            return false;
        }

        // Calculate maximum_records:
        $maximum_records = $records_per_download;
        $end_record = $start_record + $records_per_download -1;
        if ($end_record > $max_possible_desired_record) {
            $maximum_records = $max_possible_desired_record - $start_record + 1;
        }

        // Generate the query:
        $oclc_ids = array_slice($this->oclc_id_list(), $start_record - 1, $maximum_records);
        $query_expressions = array_map(create_function('$id', 'return "srw.no all \"$id\"";'), $oclc_ids);
        $query = join(' or ', $query_expressions);

        $request = new WorldCatSearch_Request(array(
            'api' => $this->api(),
            'query' => $query,
            'wskey' => $this->wskey(),
            //'startRecord' => $start_record,
            'startRecord' => 1,
            'maximumRecords' => $maximum_records,
        ));
        $this->current = $request;

        // Increment for the next iteration:    
        $this->set_current_iteration( $this->current_iteration() + 1 );

        return true;
    }

    function valid()
    {
        return $this->valid;
    }

} // end class WorldCatSearch_Request_Iterator_OCLCIdList
