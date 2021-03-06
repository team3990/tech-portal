<?php

namespace T4KControllers\Events;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Mail;

/**
 * T4KControllers\Events\EventsController class
 * @author      minhnhatbui
 * @copyright   2014 Équipe Team 3990: Tech for Kids (Collège Regina Assumpta, Montréal, QC)
 * @abstract    View Controller for the Event model.
 */

class EventsController extends \BaseController { 
    
    /**
     * Constructor.
     */
    public function __construct() {
        $this->beforeFilter('csrf', array('on' => 'post'));
        $this->beforeFilter('auth');
        setlocale(LC_ALL, 'fr_CA.UTF-8');
    }
    
    /**
     * Display all current and upcoming events items.
     * @param int $id = null
     * @return View Response
     */
	public function upcoming($id = null)
	{
	    // Retrieve upcoming events items
	    $upcoming_events = \T4KModels\Event::upcoming()->get();
	    
	    // Retrieve current events items
	    $current_events = \T4KModels\Event::current()->get();
	    
	    // If item is select, retrieve current item
	    $event = null;
	    if (isset($id))
	    {
	        $event = \T4KModels\Event::find($id);
	    }
	    
	    // Array of data to send to view
	    $data = array(
                'upcoming_events'   => $upcoming_events,
                'current_events'    => $current_events,
                'event'             => $event,
                'ItemsCount'        => \T4KModels\Event::count(),
                'currentRoute'      => \Route::currentRouteName(),
                'activeScreen'      => 'EventsIndex'
	    );
	    
	    // Render view
	    $this->layout->content = \View::make('events.index', $data);
	}
	
	/**
	 * Display all past events items.
	 * @param int $id = null
	 * @return View Response
	 */
	public function past($id = null)
	{	     
	    // Retrieve past events items
	    $past_events = \T4KModels\Event::past()->paginate($this->ItemsPerPage*2);
	    
	    // If item is select, retrieve current item
	    $event = null;
	    if (isset($id))
	    {
	        $event = \T4KModels\Event::find($id);
	    }
	     
	    // Array of data to send to view
	    $data = array(
	            'past_events'       => $past_events,
	            'event'             => $event,
	            'ItemsCount'        => \T4KModels\Event::count(),
	            'currentRoute'      => \Route::currentRouteName(),
	            'activeScreen'      => 'EventsIndex'
	    );
	     
	    // Render view
	    $this->layout->content = \View::make('events.index', $data);
	}
	
	/**
	 * Create a event item.
	 * @return View Response
	 */
	public function create()
	{
	    // Array of data to send to view
	    $data = array(
                'currentRoute'  => \Route::currentRouteName(),
                'activeScreen'  => 'EventsIndex'
	    );
	     
	    // Render view
	    $this->layout->content = \View::make('events.form', $data);
	}
	
	/**
	 * Post the new event item in DB.
	 * @return Response
	 */
	public function store()
	{
	    // Validation rules
	    $validator = Validator::make(Input::all(), \T4KModels\Event::$rules, \T4KModels\Event::$messages);
	
	    // Validator check
	    if ($validator->fails())
	    {
	        // Throw error and redirect to previous screen
	        return Redirect::route('portal.events.create')->withErrors($validator)->withInput();
	    }
	    else
	    {
	        // Create new object from model and save it
	        $event = new \T4KModels\Event;
	        $event->user_id        = Auth::user()->id;
	        $event->datetime_start = Input::get('datetime_start');
	        $event->datetime_end   = Input::get('datetime_end');
	        $event->title          = Input::get('title');
	        $event->content        = Input::get('content');
	        $event->save();
	        $event = \T4KModels\Event::find($event->id);
	        
	        // Send email: preparing data
	        $mail_subject = $event->title.', le '.mb_strtolower(strftime('%A %e %B %Y, de %H h %M', strtotime($event->datetime_start))).' à '.mb_strtolower(strftime('%H h %M', strtotime($event->datetime_end)));
	        $users = \T4KModels\User::get();
    	    $link = (Input::get('datetime_end') >= date('Y-m-d H:i:s')) ? route('portal.events.upcoming', $event->id) : $link = route('portal.events.past', $event->id);
	        
	        // Sending email to each user
	        foreach ($users as $user)
	        {
	            if ($user->is_mentor || $user->is_junior_mentor || $user->is_student)
	            {
    	            // Array of data to send to email
    	            $data = array(
    	                    'event'        => $event,
    	                    'user'         => $user,
    	                    'link'         => $link
    	            );
    	            
    	            // Sending mail
        	        Mail::send('emails.events.new', $data, function($message) use ($user, $mail_subject)
        	        {
        	            $message->from('no-reply@team3990.com', 'Equipe Team 3990: Tech for Kids');
        	            $message->to($user->email);
        	            $message->subject($mail_subject);
        	        });
	            }
	        }
	
	        // Redirect to view screen with success message
	        Session::flash('store', true);
	        if (Input::get('datetime_end') >= date('Y-m-d H:i:s'))
	        {
	           return Redirect::route('portal.events.upcoming', $event->id);
	        }
	        else 
	        {
	            return Redirect::route('portal.events.past', $event->id);
	        }
	    }
	}
	
	/**
	 * Modify an existing event item.
	 * @param int $id
	 * @return View Response
	 */
	public function edit($id)
	{
	    // Retrieve the event item with its id
	    $event = \T4KModels\Event::find($id);
	     
	    // Array of data to send to view
	    $data = array(
                'event'         => $event,
	            'currentRoute'  => \Route::currentRouteName(),
                'activeScreen'  => 'EventsIndex'
	    );
	     
	    // Render view
	    $this->layout->content = \View::make('events.form', $data);
	}
	
	/**
	 * Post the updated event item to the DB.
	 * @param int $id
	 * @return Response
	 */
	public function update($id)
	{
	    // Validation rules
	    $validator = Validator::make(Input::all(), \T4KModels\Event::$rules, \T4KModels\Event::$messages);
	
	    // Validator check
	    if ($validator->fails())
	    {
	        // Throw error and redirect to previous screen
	        return Redirect::route('portal.events.edit', $id)->withErrors($validator)->withInput();
	    }
	    else
	    {
	        // Retrieve object from model and update it
	        $event = \T4KModels\Event::find($id);
	        $event->datetime_start = Input::get('datetime_start');
	        $event->datetime_end   = Input::get('datetime_end');
	        $event->title          = Input::get('title');
	        $event->content        = Input::get('content');
	        $event->save();
	
	        // Redirect to view screen with success message
	        Session::flash('update', true);
            if (Input::get('datetime_end') >= date('Y-m-d H:i:s'))
            {
               return Redirect::route('portal.events.upcoming', $event->id);
            }
            else 
            {
                return Redirect::route('portal.events.past', $event->id);
            }
	    }
	}
	
	/**
	 * Soft destroy a event item.
	 * @param int $id
	 * @return Response
	 */
	public function destroy($id)
	{
	    // Retrieve object
	    $event = \T4KModels\Event::where('id', $id)->first();
	    Session::flash('object_name', $event->title);
	    
	    // Delete object
	    $event->delete();
	
	    // Redirect to view screen with success message
	    Session::flash('destroy', true);
	    return Redirect::route('portal.events.index');
	}
	
	/**
	 * Confirm the current user's attendance information.
	 * @param int $id
	 * @return Response
	 */
	public function confirm()
	{
	    
	    // Check if there is already an attendance confirmation
	    $confirmation = \T4KModels\EventPresence::
	             where('event_id', Input::get('id'))
	           ->where('user_id', \Auth::user()->id)
	           ->orderBy('id', 'desc')
	           ->first();
	    
	    if (isset($confirmation))
	    {
	        $confirmation->datetime_start  = Input::get('date_event').' '.Input::get('time_start').':00';
	        $confirmation->datetime_end    = Input::get('date_event').' '.Input::get('time_end').':00';
	        $confirmation->is_attending    = Input::get('attending');
	        $confirmation->save();
	    }
	    else
	    {
	        $confirming = new \T4KModels\EventPresence;
	        $confirming->datetime_start    = Input::get('date_event').' '.Input::get('time_start').':00';
	        $confirming->datetime_end      = Input::get('date_event').' '.Input::get('time_end').':00';
	        $confirming->is_attending      = Input::get('attending');
	        $confirming->user_id           = \Auth::user()->id;
	        $confirming->event_id          = Input::get('id');
	        $confirming->save();
	    }
	    
	    // Redirect to view screen with success message
	    return Redirect::route(Input::get('view'), array(Input::get('id'), 'page' => Input::get('page')));
	    
	}
	
	/**
	 * Catch-all method for handling missing methods.
	 * @return Redirect Response
	 */
	public function missingMethod($parameters = array())
	{
	    // Array of data to send to view
	    $data = array(
                'currentRoute'  => \Route::currentRouteName(),
                'activeScreen'  => 'EventsIndex'
	    );
	    
	    // Redirect to Dashboard
	    return Redirect::route('portal.events.index', $data);
	}

}
