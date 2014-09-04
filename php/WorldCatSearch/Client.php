<?php

require_once 'ArgValidator.php';

class WorldCatSearch_Client
{
    protected $request_iterator;
    function set_request_iterator($request_iterator)
    {
        // TODO: Fix inheritance of the *Iterator classes so that this will work:
        /*
        ArgValidator::validate(
            func_get_args(), array(array('instanceof' => WorldCatSearch_Request_Iterator))
        );
        */
        $this->request_iterator = $request_iterator;
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

    protected $file_set;
    function set_file_set($file_set)
    {
        ArgValidator::validate(
            func_get_args(), array(array('instanceof' => 'Set'))
        );
        $this->file_set = $file_set;
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
            array('request_iterator' => array('required' => true),)
        );

        foreach ($validated_args as $property => $value) {
            $mutator = 'set_' . $property;
            $this->$mutator( $value );
        }

        if ($this->user_agent() === null) {
            $ref_class = new ReflectionClass( get_class($this->request_iterator()) );
            if (!$ref_class->hasProperty('user_agent')) {
                // TODO: Bad assumption here that this method will exist, due to use of magic methods:
                $user_agent = $this->request_iterator()->user_agent();
            } else {
                require_once 'WorldCatSearch/UserAgent.php';
                $user_agent = new WorldCatSearch_UserAgent();
            }
            $this->set_user_agent( $user_agent );
        }
    }
    
    public function search()
    {
        $ua = $this->user_agent();

        $ri = $this->request_iterator();
        $ri->rewind();

        while ($ri->valid()) {
            $request = $ri->current();
            try {
                // TODO: Make this a proper response object!!!
                $response = $ua->send($request);
            } catch (Exception $e) {
                error_log( $e->getMessage );
            }
            if (!isset($e)) {
                // TODO: Support for just printing to STDOUT?
                $file_name = $this->file_set()->add(); 
                file_put_contents($file_name, $response);
                // TODO: What to do about this? What to return, if anything?
                //$file_names[] = $file_name;
            }
            $ri->next();
        }
    }

} // end class WorldCatSearch_Client
