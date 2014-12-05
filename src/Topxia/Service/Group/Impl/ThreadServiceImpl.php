<?php

namespace Topxia\Service\Group\Impl;

use Topxia\Service\Common\BaseService;
use Topxia\Service\Group\GroupService;
use Topxia\Service\Group\ThreadService;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\File\File;

class ThreadServiceImpl extends BaseService implements ThreadService {

    public function getThread($id)
    {
        return $this->getThreadDao()->getThread($id);
    }

     public function isCollected($userId, $threadId)
    {
        $thread = $this->getThreadCollectDao()->getThreadByUserIdAndThreadId($userId, $threadId);
        if(empty($thread)) {
            return false;
        } else {
            return true;
        }
    }

    public function getThreadsByIds($ids)
    {
        $threads=$this->getThreadDao()->getThreadsByIds($ids);
        return ArrayToolkit::index($threads, 'id');
    }

    public function threadCollect($userId,$threadId)
    {
        $thread = $this->getThread($threadId);
        if(empty($thread)) {
            throw $this->createServiceException('话题不存在，收藏失败！');
        }
        if($userId == $thread['userId']) {
            throw $this->createServiceException('不能收藏自己的话题！');
        }
        $collectThread =  $this->getThreadCollectDao()->getThreadByUserIdAndThreadId($userId, $threadId);
        if(!empty($collectThread)) {
            throw $this->createServiceException('不允许重复收藏!');
        }
        return $this->getThreadCollectDao()->addThreadCollect(array(
            "userId"=>$userId,
            "threadId"=>$threadId,
            "createdTime"=>time()));
    }

    public function searchHides($conditions,$orderBy,$start,$limit)
    {
        return $this->getThreadHideDao()->searchHides($conditions,$orderBy,$start,$limit);
    }

    public function unThreadCollect($userId,$threadId)
    {
        $thread = $this->getThread($threadId);
        if(empty($thread)) {
            throw $this->createServiceException('话题不存在，取消收藏失败！');
        }
        $collectThread =  $this->getThreadCollectDao()->getThreadByUserIdAndThreadId($userId, $threadId);
        if(empty($collectThread)) {
            throw $this->createServiceException('不存在此收藏关系，取消收藏失败！');
        }
        return $this->getThreadCollectDao()->deleteThreadCollectByUserIdAndThreadId($userId,$threadId);
    }

    public function searchThreadCollectCount($conditions)
    {
        return $this->getThreadCollectDao()->searchThreadCollectCount($conditions);
    }


    public function searchThreadsCount($conditions)
    {
        $conditions=$this->prepareThreadConditions($conditions);
        $count=$this->getThreadDao()->searchThreadsCount($conditions);
        return $count;
    }

    public function searchPostsThreadIds($conditions,$orderBy,$start,$limit)
    {
        return $this->getThreadPostDao()->searchPostsThreadIds($conditions,$orderBy,$start,$limit);
    }

    public function searchThreadCollects($conditions,$orderBy,$start,$limit)
    {
        return $this->getThreadCollectDao()->searchThreadCollects($conditions,$orderBy,$start,$limit);
    }

    public function searchPostsThreadIdsCount($conditions)
    {
        return $this->getThreadPostDao()->searchPostsThreadIdsCount($conditions);
    }

    public function getbuyHideByUserIdandHideId($userId,$hideId)
    {
        return $this->getThreadBuyHideDao()->getbuyHideByUserIdandHideId($userId,$hideId);
    }

    public function getPost($id)
    {
         return $this->getThreadPostDao()->getPost($id);
    }

    public function addThread($thread) 
    {     
        if (empty($thread['title'])) {
            throw $this->createServiceException("标题名称不能为空！");
        }
        $thread['title'] = $this->purifyHtml(empty($thread['title']) ? '' : $thread['title']);

        if (empty($thread['content'])) {
            throw $this->createServiceException("话题内容不能为空！");
        }
        $thread['content'] = $this->purifyHtml(empty($thread['content']) ? '' : $thread['content']);
        
        if (empty($thread['groupId'])) {
            throw $this->createServiceException("小组Id不能为空！");
        }
        if (empty($thread['userId'])) {
            throw $this->createServiceException("用户ID不能为空！");
        }
        $thread['createdTime']=time();
        $thread=$this->getThreadDao()->addThread($thread);

        $this->getGroupService()->waveGroup($thread['groupId'],'threadNum',+1);

        $this->getGroupService()->waveMember($thread['groupId'],$thread['userId'],'threadNum',+1);
        
        $this->hideThings($thread['content'],$thread['id']);

        return $thread;
    }

    public function addAttach($fileIds,$fileTitles,$fileDescriptions,$fileCoins,$threadId)
    {
        $fileIds=explode(",", $fileIds);
        $fileTitles=explode(",", $fileTitles);
        $fileCoins=explode(",", $fileCoins);
        $fileDescriptions=explode("end!,", $fileDescriptions);

        $user = $this->getCurrentUser();
        for($i=0;$i<count($fileIds);$i++){

            $file=$this->getFileService()->getFile($fileIds[$i]);

            if($file['userId'] != $user->id) continue;
            
            $hide=$this->getThreadHideDao()->searchHides(array('threadId'=>$threadId,'fileId'=>$fileIds[$i]),array('createdTime','desc'),0,1);
            
            $fileTitles[$i]=$this->sub_txt($fileTitles[$i]);

            if($i == count($fileIds) - 1 )
            {
                $fileDescriptions[$i]=substr($fileDescriptions[$i], 0, -4);
            }

            $attach=array(
                'title'=>$fileTitles[$i],
                'description'=>$fileDescriptions[$i],
                'type'=>'attachment',
                'threadId'=>$threadId,
                'coin'=>$fileCoins[$i],
                'fileId'=>$fileIds[$i],
                'createdTime'=>time(),
                );

            if($hide){

                $this->getThreadHideDao()->updateHide($hide[0]['id'],$attach);
                continue;

            }
            $this->getThreadHideDao()->addHide($attach);
        }
    }

    protected function hideThings($content,$id)
    {
        $data=explode('[/hide]', $content);
     
        foreach ($data as $key => $value) {

            $value=" ".$value;
            sscanf($value,"%[^[][hide=coin%[^]]]%[^$$]",$content,$coin,$title);

            if($coin >0 && $title !="" ){

                $hide=array(
                    'title'=>$title,
                    'type'=>'content',
                    'threadId'=>$id,
                    'coin'=>$coin,
                    'createdTime'=>time());
                $this->getThreadHideDao()->addHide($hide);
            }

            unset($coin);
            unset($title);
        }

    }

    private function sub_txt($string)
    {
        $string=explode(".", $string);
       
        $length=10;
        $text=$string[0];
        $text = strip_tags($text);

        $text = str_replace(array("\n", "\r", "\t") , '', $text);
        $text = str_replace('&nbsp;' , ' ', $text);
        $text = trim($text);

        $length = (int) $length;
        if ( ($length > 0) && (mb_strlen($text,'utf-8') > $length) )  {
            $text = mb_substr($text, 0, $length, 'UTF-8');
        }

        return $text.".".$string[1];
    }

    public function getHide($id)
    {
        return $this->getThreadHideDao()->getHide($id);
    }

    public function getCoinByThreadId($id)
    {   
        $condition=array('threadId'=>$id);
        return $this->getThreadHideDao()->getCoinByThreadId($condition);
    }

    public function waveHitNum($threadId)
    {
        $this->getThreadDao()->waveThread($threadId,'hitNum',+1);
    }

    public function addBuyHide($fields)
    {   
        if (empty($fields['userId'])) {
            throw $this->createServiceException("用户ID不能为空!");
        }

        return $this->getThreadBuyHideDao()->addBuyHide($fields);
    }

    public function updateThread($id,$fields)
    {
        if (empty($fields['title'])) {
            throw $this->createServiceException("标题名称不能为空！");
        }
        if (empty($fields['content'])) {
            throw $this->createServiceException("话题内容不能为空！");
        }

        $this->getThreadHideDao()->deleteHideByThreadId($id,'content');
        $this->hideThings($fields['content'],$id);

        return $this->getThreadDao()->updateThread($id,$fields);
    }

    public function closeThread($threadId)
    {
         $this->getThreadDao()->updateThread($threadId,array('status'=>'close'));
    }

    public function openThread($threadId)
    {
         $this->getThreadDao()->updateThread($threadId,array('status'=>'open'));
    }

    public function searchThreads($conditions,$orderBy,$start, $limit)
    {
        $conditions=$this->prepareThreadConditions($conditions);
        return $this->getThreadDao()->searchThreads($conditions,$orderBy,$start,$limit);
    }

    public function postThread($threadContent,$groupId,$memberId,$threadId,$postId=0)
    {
        if (empty($threadContent['content'])) {
            throw $this->createServiceException("回复内容不能为空！");
        }
        $threadContent['content']=$this->purifyHtml($threadContent['content']);
        $threadContent['userId']=$memberId;
        $threadContent['fromUserId']=$threadContent['fromUserId'];
        $threadContent['createdTime']=time();
        $threadContent['threadId']=$threadId;
        $threadContent['postId']=$postId;
        $post=$this->getThreadPostDao()->addPost($threadContent);  
        $this->getThreadDao()->updateThread($threadId,array('lastPostMemberId'=>$memberId,'lastPostTime'=>time()));
        $this->getGroupService()->waveGroup($groupId,'postNum',+1);
        $this->getGroupService()->waveMember($groupId,$memberId,'postNum',+1);
        $this->waveThread($threadId,'postNum',+1);
        $thread=$this->getThread($threadId); 
        return $post;
    }

    public function searchPosts($conditions,$orderBy,$start,$limit)
    {
        $conditions = $this->prepareThreadConditions($conditions);
        return $this->getThreadPostDao()->searchPosts($conditions,$orderBy,$start,$limit);

    }

    public function searchPostsCount($conditions)
    {
        $conditions = $this->prepareThreadConditions($conditions);
        $count= $this->getThreadPostDao()->searchPostsCount($conditions);
        return $count;
    }

    public function setElite($threadId)
    {
        $this->getThreadDao()->updateThread($threadId,array('isElite'=>1));
    }

    public function removeElite($threadId)
    {
        $this->getThreadDao()->updateThread($threadId,array('isElite'=>0));
    }

    public function setStick($threadId)
    {
        $this->getThreadDao()->updateThread($threadId,array('isStick'=>1));
    }

    public function removeStick($threadId)
    {
        $this->getThreadDao()->updateThread($threadId,array('isStick'=>0));
    }

    public function deleteThread($threadId)
    {
         $thread=$this->getThreadDao()->getThread($threadId);
         $this->deletePostsByThreadId($threadId);
         $this->getThreadDao()->deleteThread($threadId);

         $this->getGroupService()->waveGroup($thread['groupId'],'threadNum',-1);

         $this->getGroupService()->waveMember($thread['groupId'],$threadId,'threadNum',-1);

    }

    public function updatePost($id,$fields)
    {
        return $this->getThreadPostDao()->updatePost($id,$fields);
    }

    public function deletePost($postId)
    {
        $post=$this->getThreadPostDao()->getPost($postId);
        $threadId=$post['threadId'];
        $thread=$this->getThreadDao()->getThread($threadId);

        $this->getThreadPostDao()->deletePost($postId);

        $this->getGroupService()->waveGroup($thread['groupId'],'postNum',-1);

        $this->getGroupService()->waveMember($thread['groupId'],$threadId,'postNum',-1);

        $this->waveThread($threadId,'postNum',-1);
 
    }

    public function deletePostsByThreadId($threadId)
    {
        $thread=$this->getThreadDao()->getThread($threadId);
        $postCount=$this->getThreadPostDao()->searchPostsCount(array('threadId'=>$threadId));

        $this->getGroupService()->waveGroup($thread['groupId'],'postNum',-$postCount);

        $this->getGroupService()->waveMember($thread['groupId'],$threadId,'postNum',-$postCount);

        $this->getThreadPostDao()->deletePostsByThreadId($threadId);
    }

    public function getBuyHide($id)
    {
        return $this->getThreadBuyHideDao()->getBuyHide($id);
    }

    private function waveThread($id,$field, $diff)
    {
        return $this->getThreadDao()->waveThread($id, $field, $diff);

    }
    private function getLogService() 
    {
        return $this->createService('System.LogService');
    }

     private function prepareThreadConditions($conditions)
     {
        if(isset($conditions['groupName'])&&$conditions['groupName']!==""){
            $group=$this->getGroupService()->findGroupByTitle($conditions['groupName']);
            if(!empty($group)){
              $conditions['groupId']=$group[0]['id'];  
            }else{
              $conditions['groupId']=0;  
            }   
        }

        if(isset($conditions['userName'])&&$conditions['userName']!==""){
            $user=$this->getUserService()->getUserByNickname($conditions['userName']);
            if(!empty($user)){
              $conditions['userId']=$user['id'];  
            }else{
              $conditions['userId']=0;  
            }   
        }

         if(isset($conditions['status']))
        {
            if($conditions['status']==""){
               unset( $conditions['status']);
            }
        }   
        return $conditions;
    }

    public function getbuyHideByUserIdandThreadId($id,$userId)
    {
        return $this->getThreadBuyHideDao()->getbuyHideByUserIdandThreadId($id,$userId);
    }

    private function getThreadBuyHideDao()
    {
        return $this->createDao('Group.ThreadBuyHideDao');
    }

    private function getThreadHideDao()
    {
        return $this->createDao('Group.ThreadHideDao');
    }

    private function getThreadDao()
    {
        return $this->createDao('Group.ThreadDao');
    }
    private function getGroupService()
    {
        return $this->createService('Group.GroupService');
    }
    private function getUserService()
    {
        return $this->createService('User.UserService');
    }
    protected function getFileService()
    {
        return $this->createService('Content.FileService');
    }
    private function getThreadPostDao()
    {
        return $this->createDao('Group.ThreadPostDao');
    }
    private function getThreadCollectDao()
    {
        return $this->createDao('Group.ThreadCollectDao');
    }
    private function getMessageService() 
    {
        return $this->createService('User.MessageService');
    }
}
