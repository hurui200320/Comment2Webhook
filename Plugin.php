<?php
/**
 * 评论时触发Webhook。
 * 
 * @package Comment2Webhook
 * @author 天空Blond
 * @version 1.0.0
 * @link https://skyblond.info
 */
class Comment2Webhook_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('Comment2Webhook_Plugin', 'handleComment');
        Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = array('Comment2Webhook_Plugin', 'handleComment');
        Typecho_Plugin::factory('Widget_Service')->sendWebhook = array('Comment2Webhook_Plugin', 'doAsync');
        
        return _t('请配置此插件的 webhook_url 以使您的评论推送生效');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $webhook_url = new Typecho_Widget_Helper_Form_Element_Text('webhook_url', NULL, NULL, _t('webhook_url'), _t('webhook_url 是插件在收到评论时发送POST请求的url'));
        $form->addInput($webhook_url->addRule('required', _t('webhook_url 不能为空')));
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

	public static function copyComment(&$result, $comment, $prefix) {
		$result[''.$prefix.'cid'] = $comment->cid;
		$result[''.$prefix.'coid'] = $comment->coid;
		$result[''.$prefix.'created'] = $comment->created;
		$result[''.$prefix.'author'] = $comment->author;
		$result[''.$prefix.'authorId'] = $comment->authorId;
		$result[''.$prefix.'ownerId'] = $comment->ownerId;
		$result[''.$prefix.'mail'] = $comment->mail;
		$result[''.$prefix.'ip'] = $comment->ip;
		$result[''.$prefix.'title'] = $comment->title;
		$result[''.$prefix.'text'] = $comment->text;
		$result[''.$prefix.'permalink'] = $comment->permalink;
		$result[''.$prefix.'status'] = $comment->status;
		$result[''.$prefix.'parent'] = $comment->parent;
	}
	
	// copied from https://github.com/AlanDecode/Typecho-Plugin-Mailer/blob/0e13e004bb70c4d0e7decc5372e9626c97e2acb2/Plugin.php#L300
	public static function widgetById($table, $pkId)
    {
        $table = ucfirst($table);
        if (!in_array($table, array('Contents', 'Comments', 'Metas', 'Users'))) {
            return NULL;
        }

        $keys = array(
            'Contents'  =>  'cid',
            'Comments'  =>  'coid',
            'Metas'     =>  'mid',
            'Users'     =>  'uid'
        );

        $className = "Widget_Abstract_{$table}";
        $key = $keys[$table];
        $db = Typecho_Db::get();
        $widget = $className::alloc();
        
        $db->fetchRow($widget->select()->where("{$key} = ?", $pkId)->limit(1),
                array($widget, 'push'));

        return $widget;
    }

    /**
     * 处理评论
     * 
     * @access public
     * @param array $comment 评论结构
     * @return void
     */
    public static function handleComment($comment)
    {
		// copy the comment
		$result = array();
        self::copyComment($result, $comment, "current_");
        if($comment->parent) {
            $p = self::widgetById('comments', $comment->parent);
            $parentComment = self::copyComment($result, $p, "parent_");
        }

        Helper::requestService('sendWebhook', $result);
    }
	
    public static function doAsync($postdata)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $webhook_url = $options->plugin('Comment2Webhook')->webhook_url;

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($postdata)
                )
            );
        $context = stream_context_create($opts);
        $result = file_get_contents($webhook_url, false, $context);
    }
}
