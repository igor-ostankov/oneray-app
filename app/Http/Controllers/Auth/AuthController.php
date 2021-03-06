<?php namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Repositories\GuestTokenRepository;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Mail;
use Session;
use Auth;
use Validator;

use App\Workspace;

class AuthController extends Controller {

	/*
	|--------------------------------------------------------------------------
	| Registration & Login Controller
	|--------------------------------------------------------------------------
	|
	| This controller handles the registration of new users, as well as the
	| authentication of existing users. By default, this controller uses
	| a simple trait to add these behaviors. Why don't you explore it?
	|
	*/

	use AuthenticatesAndRegistersUsers;

	protected $loginPath = '/login';
	protected $redirectTo = '/';

	/**
	 * Create a new authentication controller instance.
	 *
	 * @param  \App\Workspace $workspace
	 * @param  \Illuminate\Contracts\Auth\Guard  $auth
	 * @param  \Illuminate\Contracts\Auth\Registrar  $registrar
	 * @return void
	 */
	public function __construct()
	{
		$this->middleware('guest', ['except' => 'getLogout']);
	}

	public function validator(array $data)
	{
		return Validator::make($data, [
			'first_name' => 'required|max:255',
			'last_name' => 'required|max:255',
			'email' => 'required|email|max:255|unique:users',
			'password' => 'required|confirmed|min:6',
		]);
	}

	public function create(array $data)
	{
		return User::create([
			'first_name' => $data['first_name'],
			'last_name' => $data['last_name'],
			'email' => $data['email'],
			'password' => bcrypt($data['password']),
		]);
	}

	public function getLogin() {
		return view('auth.login');
	}

	public function postLogin(Request $request, Workspace $workspace)
	{
		$this->validate($request, [
			'email' => 'required|email', 'password' => 'required',
		]);

		$credentials = $request->only('email', 'password');

		if (Auth::attempt($credentials, false, false)
			&& $workspace->users->contains(Auth::getLastAttempted()))
		{
			Auth::login(Auth::getLastAttempted(), $request->has('remember'));
			return redirect($this->redirectPath());
		}

		return redirect($this->loginPath())
			->withInput($request->only('email', 'remember'))
			->withErrors([
				'email' => $this->getFailedLoginMessage(),
			]);
	}

	public function getRegister() {
		return view('auth.register.email');
	}

	public function postRegister(Request $request, Workspace $workspace) {

		$email = $request->input('email').'@'.$workspace->domain;
		$request->replace(['email' => $email]);
		$this->validate($request, [
			'email' => 'required|email'
		]);

		if($workspace->users->where('email', $email)->first())
			return view('auth.register.alreadyRegistered');

		$tokenRepository = new GuestTokenRepository($workspace);
		$token = $tokenRepository->create($email);
		Mail::send('emails.auth.emailConfirmation', compact('token'), function($message) use ($email) {
			$message->to($email);
		});
		return redirect('register/email')->with('email', $email);
	}

	public function storeRegister(Request $request, Workspace $workspace) {
		$token = $request->route('token');
		$tokenRepository = new GuestTokenRepository($workspace);
		if(!$tokenRepository->exists($token))
			abort(404);

		$email = $tokenRepository->getEmail($token);
		$user = User::where(['email' => $email])->first();
		if($user) {
			$user->workspaces()->attach($workspace->id);
		} else {
			if($request->getMethod() == 'POST') {
				$this->validate($request, [
					'first_name' => 'required|max:255',
					'last_name' => 'required|max:255',
					'password' => 'required|confirmed|min:6',
				]);

				$user = new User;
				$user->first_name = $request->get('first_name');
				$user->last_name = $request->get('last_name');
				$user->email = $email;
				$user->password = bcrypt($request->get('password'));
				$user->save();

				$user->workspaces()->attach($workspace->id);
			} else {
				return view('auth.register.create');
			}
		}

		Auth::login($user);
		$tokenRepository->delete($token);
		return redirect('/');

	}

	public function getMailSent() {
		$email = Session::get('email');
		if(!$email)
			return redirect('/');
		return view('auth.register.checkEmail', ['email' => $email]);
	}

}
