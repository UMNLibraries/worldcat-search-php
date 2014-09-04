<?php

require_once 'WorldCatSearch/Request/Iterator.php';
require_once 'ArgValidator.php';

class WorldCatSearch_Request_Iterator_Generic extends WorldCatSearch_Request_Iterator
{
    protected $query;
    function set_query($query)
    {
        ArgValidator::validate( $query, array('is' => 'string') );
        $this->query = $query;
    }
    // 'max_records' is a limit on the total number of records to extract.
    // If not set, will default to worldcat_max_records.
    protected $max_records;
    function set_max_records($max_records)
    {
        ArgValidator::validate( $max_records, array('is' => 'int') );
        $this->max_records = $max_records;
    }

    // This will be calulated in __construct():
    protected $query_record_count = 0;
    protected function set_query_record_count($query_record_count)
    {
        ArgValidator::validate( $query_record_count, array('is' => 'int') );
        $this->query_record_count = $query_record_count;
    }

    // This will be calulated in __construct():
    protected $max_possible_desired_record = 0;
    protected function set_max_possible_desired_record($max_possible_desired_record)
    {
        ArgValidator::validate( $max_possible_desired_record, array('is' => 'int') );
        $this->max_possible_desired_record = $max_possible_desired_record;
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
            array('wskey' => array('required' => true), 'query' => array('required' => true),)
        );

        foreach ($validated_args as $property => $value) {
            $mutator = 'set_' . $property;
            $this->$mutator( $value );
        }

        // TODO: WHY is this the only thing that causes worldcat_max_records
        // to get set, by throwing an exception?
        //$this->set_worldcat_max_records( 'bogus' );

        $worldcat_max_records = $this->worldcat_max_records();
        if ($this->max_records() === null) {
            $this->set_max_records( $worldcat_max_records );
        }

        if ($this->max_records() > $worldcat_max_records) {
            error_log("Since the WorldCat API will return no more than $worldcat_max_records records for a single query, resetting max_records to $worldcat_max_records.");
            $this->set_max_records( $worldcat_max_records );
        }

        if ($this->user_agent() === null) {
            require_once 'WorldCatSearch/UserAgent.php';
            $this->set_user_agent( new WorldCatSearch_UserAgent() );
        }

        // Get the total count of records for this query:
        $query = $this->query();
        $request = new WorldCatSearch_Request(array(
             'api' => $this->api(),
             'query' => $query,
             'wskey' => $this->wskey(),
             'startRecord' => 1,
             'maximumRecords' => 0,
        ));
        try {
            $result = $this->user_agent()->send( $request );
        } catch (Exception $e) {
            throw new Exception( "Failed to get record count: " . $e->getMessage );
        }

        $parsed_result = new SimpleXMLElement( $result );

        if ( $parsed_result->diagnostics ) {
            $message =  $parsed_result->diagnostics->diagnostic->message;
            $details =  $parsed_result->diagnostics->diagnostic->details;
            $error_message = "WorldCat API threw an exception: message: '$message'; details: '$details'\n";
            throw new Exception( $error_message );
        }

        //$this->set_query_record_count( (int) $parsed_result->numberOfRecords );
        $query_record_count = (int) $parsed_result->numberOfRecords;
        
        if ($query_record_count > $worldcat_max_records) {
            error_log("WorldCat contains $query_record_count records that match your query, '$query', but the WorldCat API will return no more than $worldcat_max_records records for a single query.");
        }

        // Calculate the effective last record index:
        $max_records = $this->max_records();
        $max_possible_desired_record = ($max_records > $query_record_count) ? 
            $query_record_count : $max_records; 

        $this->set_query_record_count( $query_record_count );
        $this->set_max_possible_desired_record( $max_possible_desired_record );
    }

    // Iterator interface implementation:

    public function rewind()
    {
        // Make sure the iterator is never valid if there are no records:
        $this->valid = $this->max_possible_desired_record() > 0 ? true : false;

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

        $request = new WorldCatSearch_Request(array(
            'api' => $this->api(),
            'query' => $this->query(),
            'wskey' => $this->wskey(),
            'startRecord' => $start_record,
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

} // end class WorldCatSearch_Request_Iterator
