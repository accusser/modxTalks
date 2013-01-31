<?php
class getCommentsListProcessor extends modObjectGetListProcessor {
    public $classKey = 'modxTalksPost';
    public $languageTopics = array('modxtalks:default');
    public $limit = 20;
    public $start = 0;
    public $conversationId = 0;

    public function beforeQuery() {
        if ($this->modx->modxtalks->config['commentsPerPage'] != 0) {
            $this->limit = $this->modx->modxtalks->config['commentsPerPage'];
        }

        $this->conversation = (string) $this->getProperty('conversation');

        /**
         * Check Conversation
         */
        if (!$this->theme = $this->modx->modxtalks->getConversation($this->conversation)) {
            return $this->failure($this->modx->lexicon('modxtalks.empty_conversationId'));
        }
        $this->conversationId = $this->theme->id;
        return parent::beforeQuery();
    }

    public function getData() {
        $data = array('total' => 0, 'results' => array());

        $count = $this->theme->getProperty('total','comments');
        if ($count < 1) return $data;

        if ($slug = $this->getProperty('slug')) {
            $this->modx->modxtalks->config['slug'] = $slug;
        }

        $this->start = $this->getProperty('start');
        if ($this->start == date('Y-m', strtotime($this->start))) {
            $idx = $this->modx->modxtalks->getDateIndex($this->conversationId,date('Y-m',strtotime($this->start)));
            $range = range($idx, $idx + $this->limit);
        }
        else {
            $this->start = (int) $this->start;
            $range = range($this->start, $this->start + $this->limit - 1);
        }

        $comments = $this->modx->modxtalks->getCommentsArray($range,$this->conversationId);

        $usersIds =& $comments[1];
        $users = array();
        if (count($usersIds)) {
            $authUsers = $this->modx->modxtalks->getUsers($usersIds);
            foreach ($authUsers as $a) {
                $users[$a['id']] = array(
                    'name'  => $a['fullname'] ? $a['fullname'] : $a['username'],
                    'email' => $a['email'],
                );
            }
        }

        $data['total'] = $count;
        $data['results'] =& $comments[0];
        $data['users'] =& $users;
        return $data;
    }


    /**
     * Iterate across the data
     *
     * @param array $data
     * @return array
     */
    public function iterate(array $data) {
        $list = array();
        $link = $this->modx->getOption('site_url');
        $users =& $data['users'];
        $hideAvatar = '';
        $hideAvatarEmail = '';
        $relativeTime = '';
        $date_format = $this->modx->modxtalks->config['mtDateFormat'];
        /**
         * Languages...
         */
        $quote_text = $this->modx->lexicon('modxtalks.quote');
        $guest_name = $this->modx->lexicon('modxtalks.guest');
        $del_by = $this->modx->lexicon('modxtalks.deleted_by');
        $restore = $this->modx->lexicon('modxtalks.restore');

        $isModerator = $this->modx->modxtalks->isModerator();

        foreach ($data['results'] as $k => $comment) {
            $funny_date = $this->modx->modxtalks->date_format(array('date' => $comment['time']));
            $index = date('Ym',$comment['time']);
            $date = date($date_format.' O',$comment['time']);
            $timeMarker = '';
            if ($comment['userId'] > 0) {
                $name = $users[$comment['userId']]['name'];
                $email = $users[$comment['userId']]['email'];
            }
            else {
                $name = $comment['username'] ? $comment['username'] : $guest_name;
                $email = $comment['useremail'] ? $comment['useremail'] : 'anonym@anonym.com';
            }

            $userId = md5($comment['userId'].$email);

            $relativeTimeComment = $this->modx->modxtalks->relativeTime($comment['time']);
            if ($relativeTime != $relativeTimeComment) {
                $timeMarker = '<div class="timeMarker" data-now="1">'.$relativeTimeComment.'</div>';
                $relativeTime = $relativeTimeComment;
            }
            /**
             * Timeago date format
             */
            $timeago = date('c',$comment['time']);
            /**
             * Prepare data for deleted comment
             */
            if ($comment['deleteTime'] > 0 && $comment['deleteUserId'] > 0) {
                $tmp = array(
                    'deleteUser'  => $users[$comment['deleteUserId']]['name'],
                    'delete_date' => date($date_format.' O',$comment['deleteTime']),
                    'funny_delete_date' => $this->modx->modxtalks->date_format(array('date' => $comment['deleteTime'])),
                    'name'  => $name,
                    'index' => $index,
                    'date'  => $date,
                    'funny_date' => $funny_date,
                    'id'  => $comment['id'],
                    'idx' => $comment['idx'],
                    'link_restore' => '',
                    'timeMarker' => $timeMarker,
                    'userId'     => $userId,
                    'timeago'    => $timeago,
                    'deleted_by' => $del_by,
                    'restore'    => $restore,
                );
            }
            /**
             * Prepare data for published comment
             */
            else {
                $tmp = array(
                    'avatar'     => $this->modx->modxtalks->getAvatar($email),
                    'hideAvatar' => ' style="display:none"',
                    'name'       => $name,
                    'content'    => $comment['content'],
                    'index'      => $index,
                    'date'       => $date,
                    'funny_date' => $funny_date,
                    'link_reply' => $this->modx->modxtalks->getLink('reply-'.$comment['idx']),
                    'id'         => $comment['id'],
                    'idx'        => $comment['idx'],
                    'userId'     => $userId,
                    'quote'      => $quote_text,
                    'user'       => $this->modx->modxtalks->userButtons($comment['userId'],$comment['time']),
                    'timeMarker' => $timeMarker,
                    'link'       => $this->modx->modxtalks->getLink($comment['idx']),
                    'funny_edit_date' => '',
                    'edit_name'  => '',
                    'timeago'    => $timeago,
                    'user_info'  => '',
                );
                if ($isModerator === true) {
                    $tmp['user_info'] = '<div class="user_info"><span class="user_ip">IP: '.$comment['ip'].'</span><span class="user_email">Email: '.$email.'</span></div>';
                }
                if ($email != $hideAvatarEmail) {
                    $tmp['hideAvatar'] = '';
                    $hideAvatarEmail = $email;
                }
                if ($comment['editTime'] && $comment['editUserId'] && !$comment['deleteTime']) {
                    $tmp['funny_edit_date'] = $this->modx->modxtalks->date_format(array('date' => $comment['editTime']));
                    $tmp['edit_name'] = $this->modx->lexicon('modxtalks.edited_by',array('name' => $users[$comment['editUserId']]['name']));
                    ;
                }
            }

            $list[] = $tmp;

        }
        return $list;
    }

}

return 'getCommentsListProcessor';