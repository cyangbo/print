<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
2015年2月8日PHP
*/

class Login extends CI_Controller{
	
	//传递到视图的数据
	private $_data;
	
	public $referrer;
	
	public function __construct(){
		parent::__construct();
		//加载控制用户登录和登出类
		$this->load->library('auth');
		$this->load->library('form_validation');
		
		//加载用户处理model,起别名users
		$this->load->model('users_mdl','users');
		
		//加载自定义的common类
		$this->load->library('common');
		
		//检查referrer,防止url注入
		$this->_check_referrer();
		
	}
	
	//检查referrer,防止url注入
	private function _check_referrer(){
		$ref = $this->input->get('ref',TRUE);
		/**登录页面提交的表单,如果$ref的值不为空,就把值赋给$this->referrer,如果为空,把/admin/dashboard赋值给$this->referrer
		 * 一般正常为空
		 * $this->referrer是登录后跳转的页面
		 *
		 * */
		$this->referrer = (!empty($ref)) ? $ref : '/admin/dashboard';
		
	}
	
	public function index(){
		
		//检查是否已经登录,如果已经登录,那么就跳转到后台
		//如果之前已经登录了,会$this->_hasLogin = TRUE;
		//$this->auth->hasLogin()返回的信息是TRUE/FALSE
		if($this->auth->hasLogin()){
		
			redirect($this->referrer);
		}  
		
		
		//前端验证输入的用户名密码
		//用户名及密码规则:
		//用户名:去掉首位空格,不为空,长度4到12位,只能是英文和数字,处理有害数据
		$this->form_validation->set_rules('name','用户名','trim|required|min_length[4]|max_length[12]|alpha_numeric|xss_clean');
		$this->form_validation->set_rules('password','密码','trim|required|xss_clean');
		//$this->form_validation->set_error_delimiters('<li>','</li>');
		$this->form_validation->set_message('required', '用户名和密码必须填写');
		$this->form_validation->set_message('min_length', '用户名必须4到12位之间');
		$this->form_validation->set_message('max_length', '用户名必须4到12位之间');
		$this->form_validation->set_message('alpha_numeric', '用户名只能是数字和字母');
		//加载错误信息的界定符
		$this->form_validation->set_error_delimiters('<p class="error-text">', '</p>');
		
		
		//如果不通过,回到登录页面
		if($this->form_validation->run() == FALSE){
			
			$this->load->view('admin/login',$this->_data);
		}
		else{
			
			if(($user = $this->users->get_by_username($this->input->post('name',TRUE)))!=0){
				
				if($this->users->check_password(
						$this->input->post('password',TRUE),
						$user['password']
						)){
					if($this->auth->process_login($user)){
						//print_r("SS");
						redirect($this->referrer);
					}
				}else{
					sleep(3);
					$this->session->set_flashdata('login_error','TRUE');
					//$this->_data['login_error'] = '密码错误';
					//$this->session->set_flashdata('login_error','登陆密码错误');
					$this->_data['login_error_msg'] = '登陆密码错误';
				}
				
			}else{
				sleep(3);
				$this->session->set_flashdata('login_error','TRUE');
				//$this->_data['login_error'] = '用户名不存在';
				//$this->session->set_flashdata('login_error','用户名不存在');
				$this->_data['login_error_msg'] = '用户名不存在';
			}
			
			$this->load->view('admin/login',$this->_data);
			/* //数据库验证登录,如果正确,那么得到用户信息,如果错误,返回FALSE
			$user = $this->users->validate_user(
						//第二个参数是可选的，如果想让取得的数据经过跨站脚本过滤（XSS Filtering），把第二个参数设为TRUE。
						$this->input->post('name',TRUE),
						$this->input->post('password',TRUE)
					);	
			//print_r($user);
			//如果登录信息正确
			if(!empty($user)){
				
				//process_login()处理登录信息,如果正确,更新登录信息,并且在auth类中设置$this->_hasLogin = TRUE;
				//表示已经登录
				if($this->auth->process_login($user)){
					//print_r("SS");
					redirect($this->referrer);
				}
				
			}	
			
			//如果用户名密码错误
			else{
				//先休眠3秒,可以稍微防止一下爆破
				sleep(3);
				
				$this->session->set_flashdata('login_error','TRUE');
				$this->_data['login_error_msg'] = '用户名或密码错误';
				$this->load->view('admin/login',$this->_data);
			} */
			
		}

	}
	
	//用户登出
	public function logout(){
		$this->auth->process_logout();
	}
} 

/*
End of file
Location:login.php
*/