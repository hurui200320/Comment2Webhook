# Comment2Webhook
Typecho plugin comment to webhook

苦于找不到合适的评论通知邮件，尤其是发邮件的插件，于是自己写了这个插件，在获得新评论时将评论的相关信息直接POST到Webhook，由其他程序进行后续处理。

## 请求

插件配置中有个`webhook_url`，插件会以`application/x-www-form-urlencoded`的格式向该URL发送POST请求。请求是异步的，因此返回结果会被忽略，虽然是异步的，但仍然建议快速返回。

> 我没写过PHP，我不知道这种异步调用在超时之后会发生什么。我的做法是在第三方程序收到请求后，立刻解析数据，解析成功后加入队列，随后立刻响应请求。然后再用别的线程慢慢处理队列。

请求的参数和`Widget_Feedback`的`finishComment`参数差不多，主要区别在于：

+ 当前评论会以`current_`作为前缀，例如当前评论的内容是`current_text`
+ 若有，回复的评论以`parent_`作为前缀，例如被回复的评论内容是`parent_text`

```php
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
```
