<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
2015年2月8日PHP
*/

//控制前端显示的功能
class Home extends ST_Controller{
	
	//当前uri
	private $_uri = '';
	
	
	//当前页码
	private $_current_page = 1;
	
	//每页条目数
	private $_limit = 5;
	
	//偏移
	private $_offset = 0;
	
	//条目总数
	private $_total_count = 0;
	
	//文章
	private $_posts = array();
	
	//分页字符串
	private $_pagination = '';
	
	
	
	public function __construct(){
		parent::__construct();
		$this->_uri = $this->uri->segment(1).'/';
		
		$this->load->model('posts_mdl');
	}
	
	//首页默认
	public function index(){
		
		

		/** 加载主题下的页面 */
		$this->load->view('index', $data);
		
	}
	
	//初始化分页参数
	private function _init_pagination($current_page){
		//当前页
		$this->_current_page = ($current_page && is_numeric($current_page)) ? intval($current_page):1;
		
		//每页多少项
		$page_size = setting_item('posts_page_size');
		$this->_limit = ($page_size && is_numeric($page_size)) ? intval($page_size) : 5;
		
		/** 偏移量 */
		$this->_offset = ($this->_current_page - 1) * $this->_limit;
		
		if($this->_offset < 0)
		{
			redirect(site_url());
		}
	}
	
	//处理加工文章格式
	private function _prepare_posts(){
		
		foreach($this->_posts as &$post){
			
			//设置文章的固定连接
			$post->permalink = site_url('posts/'.$post->slug);
			
			//文章发表日期
			$post->published = setting_item('post_date_format')
									?date(setting_item('post_date_format'),$post->created)
									:date('Y-m-d',$post->created);
			
			//根据文章pid获取对应类目
			$this->metas_mdl->get_metas($post->pid);
			
			//文章分类
			$post->categories = $this->metas_mdl->metas['category'];
			
			//文章标签
			$post->tags = $this->metas_mdl->metas['tag'];
			
			//文章摘要
			$post->excerpt = Common::get_excerpt($post->text);
			
			/** 是否存在摘要 */
			$post->more = (Common::has_break($post->text)) ? TRUE : FALSE;
			
			unset($post->slug);
			unset($post->text);
			
		}
		
	}
	
	
	/**
	 * 应用分页规则
	 *
	 * @access private
	 * @param  string  $target_uri 目标uri
	 * @param  bool  $url_friendly 开启友好url
	 * @param  string  $parament_name  页码参数 e.g ?p=1
	 * @param  string  $page  页码
	 * @return void
	 */
	private function _apply_pagination($target_uri, $url_friendly = TRUE, $parament_name = 'p')
	{
		if($this->_total_count > $this->_limit)
		{
			$this->dpagination->currentPage($this->_current_page);
			$this->dpagination->items($this->_total_count);
			$this->dpagination->limit($this->_limit);
			$this->dpagination->adjacents(2);
			$this->dpagination->target($target_uri);
			$this->dpagination->nextLabel('');
			$this->dpagination->PrevLabel('');
	
			if($url_friendly)
			{
				$this->dpagination->urlFriendly();
			}
			else
			{
				$this->dpagination->parameterName($parament_name);
			}
				
			$this->_pagination = $this->dpagination->getOutput();
		}
	}

	
	//搜索功能
	public function search(){
		//输入的关键词
		$keywords = strip_tags($this->input->get('s',TRUE));
		$page = strip_tags($this->input->get('p',TRUE));
		
		if($keywords){
			print_r($keywords);exit;
		}
		
		
		
		/** 页面初始化 */

		
		$this->load->view('index', $data);
		
	}
	
	
	/**
	 * 分类浏览
	 *
	 * @access public
	 * @param  string $slug
	 * @param  int    $page
	 * @return void
	 */
	public function category($slug, $page = 1)
	{	//如果参数$slug为空,并且参数$page不是数字
		if(empty($slug) || !is_numeric($page))
		{	//返回首页
			redirect(site_url());
		}
	
		//根据缩略名得到对应 分类信息
		$category = $this->metas_mdl->get_meta_by_slug(trim($slug));
		//如果不存在分类
		if(!$category)
		{
			show_error('分类不存在或已被管理员删除');
			exit();
		}
	
		/** 分页参数 */
		$this->_init_pagination($page);
		//根据分类缩略名获取所有对应分类的文章
		$this->_posts = $this->posts_mdl->get_posts_by_meta($slug, 'category', 'post', 'publish', 'posts.*', $this->_limit, $this->_offset)->result();
		//得到对应分类的文章数量
		$this->_total_count = $this->posts_mdl->get_posts_by_meta($slug, 'category', 'post', 'publish', 'posts.*', 10000, 0)->num_rows();
	
		if(!empty($this->_posts))
		{
			$this->_prepare_posts();
				
			$this->_apply_pagination(site_url('category/' . $slug) . '/%');
		}
	
		/** 页面初始化 */
		$data['page_title'] = $category->name;
		$data['page_description'] = $category->description;
		$data['page_keywords'] = setting_item('blog_keywords');
		$data['posts'] = $this->_posts;
		$data['parsed_feed'] = Common::render_feed_meta('category', $category->slug, $category->name);
		$data['current_view_hints'] = sprintf('%s 分类下的文章（第 %d 页 / 共 %d 篇）', $category->name, $this->_current_page, $this->_total_count);
		$data['pagination'] = $this->_pagination;
	
		$this->load->view('index', $data);
	}
	
	
	/**
	 * 按作者显示日志
	 *
	 * @access public
	 * @param  int  $uid
	 * @param  string  $page  页码
	 * @return void
	 */
	public function authors($uid, $page = 1)
	{
		if(empty($uid) || !is_numeric($uid) || !is_numeric($page))
		{
			redirect(site_url());
		}
	
		/** 分页参数 */
		$this->_init_pagination($page);
	
		$uid = intval($uid);
		$author = NULL;
	
		$this->_posts = $this->posts_mdl
		->get_posts_by_author($uid, 'post', 'publish', $this->_limit, $this->_offset)
		->result();
		$this->_total_count = $this->posts_mdl
		->get_posts_by_author($uid, 'post', 'publish', 10000, 0)
		->num_rows();
	
		if(!empty($this->_posts))
		{
			$this->_prepare_posts();
				
			list($temp) = $this->_posts;
			$author = $temp->screenName;
			unset($temp);
				
			$this->_apply_pagination(site_url('authors/' . $uid) . '/%');
		}
	
		/** 页面初始化 */
		$data['page_title'] = $author;
		$data['page_description'] = setting_item('blog_description');
		$data['page_keywords'] = setting_item('blog_keywords');
		$data['posts'] = $this->_posts;
		$data['parsed_feed'] = Common::render_feed_meta();
		$data['current_view_hints'] = sprintf('%s 所写的文章（第 %d 页 / 共 %d 篇）', $author, $this->_current_page, $this->_total_count);
		$data['pagination'] = $this->_pagination;
	
		$this->load->view('index', $data);
	}
	
	
	/**
	 * 分类浏览
	 *
	 * @access public
	 * @param  string $slug
	 * @param  int    $page
	 * @return void
	 */
	public function tag($slug, $page = 1)
	{
		if(empty($slug) || !is_numeric($page))
		{
			redirect(site_url());
		}
	
		$tag = $this->metas_mdl->get_meta_by_slug(trim($slug));
	
		if(!$tag)
		{
			show_error('标签不存在或已被主人删除');
			exit();
		}
	
		/** 分页参数 */
		$this->_init_pagination($page);
	
		$this->_posts = $this->posts_mdl->get_posts_by_meta($slug, 'tag', 'post', 'publish', 'posts.*', $this->_limit, $this->_offset)->result();
		$this->_total_count = $this->posts_mdl->get_posts_by_meta($slug, 'tag', 'post', 'publish', 'posts.*', 10000, 0)->num_rows();
	
		if(!empty($this->_posts))
		{
			$this->_prepare_posts();
				
			$this->_apply_pagination(site_url('tag/' . $slug) . '/%');
		}
	
		/** 页面初始化 */
		$data['page_title'] = $tag->name;
		$data['page_description'] = $tag->description;
		$data['page_keywords'] = setting_item('blog_keywords');
		$data['posts'] = $this->_posts;
		$data['parsed_feed'] = Common::render_feed_meta('tag', $tag->slug, $tag->name);
		$data['current_view_hints'] = sprintf('标记有标签 %s 的文章（第 %d 页 / 共 %d 篇）', $tag->name, $this->_current_page, $this->_total_count);
		$data['pagination'] = $this->_pagination;
	
		$this->load->view('index', $data);
	
	}
	
	/**
	 * 归档
	 *
	 * @access public
	 * @param  int  $year 归档年（必需）
	 * @param  int  $month 归档月（可选）
	 * @param  int  $day  归档日（可选）
	 * @param  string  $page  页码
	 * @return void
	 */
	public function archives($year, $month = NULL, $day = NULL, $page = 'p1')
	{
		if(empty($year))
		{
			redirect(site_url());
		}
	
		/** 基本参数 */
		$year = intval($year);
		$month = intval($month);
		$day  = intval($day);
		$date = $this->_archive_hints($year, $month, $day);
	
		/** 分页参数 */
		$page = str_replace('p','', $page);
		$this->_init_pagination($page);
	
		$this->_posts = $this->posts_mdl
		->get_posts_by_date($year, $month, $day, $this->_limit, $this->_offset)
		->result();
		$this->_total_count = $this->posts_mdl
		->get_posts_by_date($year, $month, $day, 10000, 0)
		->num_rows();
	
		if(!empty($this->_posts))
		{
			$this->_prepare_posts();
	
			$this->_apply_pagination(site_url($this->_uri).'/p%/');
		}
	
		/** 页面初始化 */
		$data['page_title'] = $date;
		$data['page_description'] = sprintf('日志归档：%s', $date);
		$data['page_keywords'] = setting_item('blog_keywords');
		$data['posts'] = $this->_posts;
		$data['parsed_feed'] = Common::render_feed_meta();
		$data['current_view_hints'] = sprintf('%s日志归档（第 %d 页 / 共 %d 篇）', $date, $this->_current_page, $this->_total_count);
		$data['pagination'] = $this->_pagination;
	
		$this->load_theme_view('index', $data);
	
	}
	
	
	/**
	 * 提取归档提示语
	 *
	 * @access private
	 * @param  int  $year 归档年（必需）
	 * @param  int  $month 归档月（可选）
	 * @param  int  $day  归档日（可选）
	 * @return string
	 */
	private function _archive_hints($year, $month, $day)
	{
		if($year > 0)
		{
			if($month > 0)
			{
				if($day > 0)
				{
					$month = sprintf("%02d", $month);
					$day = sprintf("%02d", $day);
					$this->_uri .= "$year/$month/$day";
	
					return date('Y年m月d日', mktime(0, 0, 0, $month, $day, $year));
				}
	
				$month = sprintf("%02d", $month);
				$this->_uri .= "$year/$month";
	
				return date('Y年m月', mktime(0, 0, 0, $month, 1, $year));
			}
				
			$this->_uri .= $year;
				
			return date('Y年', mktime(0, 0, 0, 1, 1, $year));
		}
	
		return;
	}
	
	
	
}

/*
End of file
Location:index.php
*/