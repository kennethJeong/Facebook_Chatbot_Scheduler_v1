<?php
if(!in_array($today, $yearsSchedule['dayoff'])) {
	// 계절학기 전체기간
	$termOfSeason = (($today >= $semesterW['start'] && $today <= $semesterW['end']) || ($today >= $semesterS['start'] && $today <= $semesterS['end']));
	// 정규학기 전체기간
	$termOfRegular = (($today >= $semester1['start'] && $today <= $semester1['end']) || ($today >= $semester2['start'] && $today <= $semester2['end']));
	// 정규학기 ((개강일 ~ 중간고사 시작일) && (중간고사 종료일 ~ 기말고사 시작일))
	$termOfRegularFromStartToEnd = ((($today >= $semester1['start'] && $today < $semester1['term']['mid']['start']) || ($today > $semester1['term']['mid']['end'] && $today < $semester1['term']['final']['start'])) || (($today >= $semester2['start'] && $today < $semester2['term']['mid']['start']) || ($today > $semester2['term']['mid']['end'] && $today < $semester2['term']['final']['start'])));
	
	if($termOfSeason || ($termOfRegular && $termOfRegularFromStartToEnd)) {
		if(date("H:i", $now) == '12:00' || date("H:i", $now) == '18:00') {
			$daily = array('일', '월', '화', '수', '목', '금', '토');
			$numOfDays = count($daily)-1;
			$date = date('w');
			$todayDaily = $daily[$date];
			
			$todayDailysUserkey = array();
			for($i=0; $i<count($userInfo); $i++) {
				for($j=1; $j<=$numOfDays; $j++) {
					if($userInfo[$i]['day'.$j] == $todayDaily) {
						$todayDailysUserkey[] = $userInfo[$i]['userkey'];
					}
				}
			}
			$todayDailysUserkey = array_keys(array_flip($todayDailysUserkey));
			for($i=0; $i<count($todayDailysUserkey); $i++) {
				$query = "INSERT INTO logging (userkey, year, semester, inProgress, inputTime) VALUE ('{$todayDailysUserkey[$i]}', '$thisYear', '$thisSemester', 'READ', '$inputTime')";
				$conn->query($query);
				$query = "INSERT INTO loggingRead (userkey, year, semester, inProgress, inputTime) VALUE ('{$todayDailysUserkey[$i]}', '$thisYear', '$thisSemester', 'READ', '$inputTime')";
				$conn->query($query);
				
				$query = "SELECT * FROM user WHERE userkey=".$todayDailysUserkey[$i]." AND year='$thisYear' AND semester='$thisSemester'";
				$sql4user = $conn->query($query);
				while($row4user = $sql4user->fetch_assoc()) {
					$userInfo4User[] = $row4user;
				}
				
				$query = "SELECT * FROM event WHERE userkey=".$todayDailysUserkey[$i]." AND year='$thisYear' AND semester='$thisSemester'";
				$sql4event = $conn->query($query);
				while($row4event = $sql4event->fetch_assoc()) {
					$eventDate1 = date("Y-m-d", mktime(0, 0, 0, (int)substr($row4event['date1'], 0, 2), (int)substr($row4event['date1'], 2, 4), date("Y")));
					$eventDate2 = date("Y-m-d", mktime(0, 0, 0, (int)substr($row4event['date2'], 0, 2), (int)substr($row4event['date2'], 2, 4), date("Y")));
					$nowDate = date("Y-m-d", strtotime($inputTime));
					if((empty($row4event['date2']) && $eventDate1 >= $nowDate) || (!empty($row4event['date2']) && $eventDate2 >= $nowDate)) {
						$eventInfo4User[] = $row4event;
					}
				}
				
				$userName = findUserName($todayDailysUserkey[$i]);
				if(date("H:i", $now) == '12:00') {
					$send['text'] =  "🎩: " . $userName . "님!\n오늘 오전 수업에 과제∙휴강∙시험은 없었나요?\n등록해주시면 제가 관리해드릴게요!✨";
				}
				else if(date("H:i", $now) == '18:00') {
					$send['text'] =  "🎩: " . $userName . "님!\n오늘 오후 수업에 과제∙휴강∙시험은 없었나요?\n등록해주시면 제가 관리해드릴게요!✨";
				}
				message($send, $todayDailysUserkey[$i], 'UPDATE');			
					
				$rgstedInfoDetail = registedConditionSubjectDetail($userInfo4User);
				for($j=0; $j<count($rgstedInfoDetail['title']); $j++) {
					$title = $rgstedInfoDetail['titleName'][$j];
					$class = $rgstedInfoDetail['class'][$j];
					$prof = $rgstedInfoDetail['prof'][$j];
					$send['title'][] = $rgstedInfoDetail['title'][$j];
					$send['subtitle'][] = $rgstedInfoDetail['info'][$j];
					$send['payload'][] = array("assignment_{$title}_{$class}_{$prof}", "cancel_{$title}_{$class}_{$prof}", "exam_{$title}_{$class}_{$prof}");
					
					$eventInfoTypes[$j] = array();
					for($k=0; $k<count($eventInfo4User); $k++) {
						if($eventInfo4User[$k]['title'] == $title) {
							$eventInfoTypes[$j][$k] = $eventInfo4User[$k]['type'];
						}
					}
					$countTypes = array_count_values($eventInfoTypes[$j]);
					$send['buttonsTitle'][$j] = array();
					is_array($countTypes) && $countTypes['assignment'] > 0 ? array_push($send['buttonsTitle'][$j], "과제({$countTypes['assignment']}개)") : array_push($send['buttonsTitle'][$j], "과제");
					is_array($countTypes) && $countTypes['cancel'] > 0 ? array_push($send['buttonsTitle'][$j], "휴강({$countTypes['cancel']}개)") : array_push($send['buttonsTitle'][$j], "휴강");
					is_array($countTypes) && $countTypes['exam'] > 0 ? array_push($send['buttonsTitle'][$j], "시험({$countTypes['exam']}개)") : array_push($send['buttonsTitle'][$j], "시험");
				}
				messageTemplateLeftSlide($send, $todayDailysUserkey[$i], 'UPDATE');	
				
				$send['text'] = "🎩: 초기화면으로 돌아가려면 아래 버튼을 눌러주세요.";
				$send['payload'] = $send['title'] = array('초기화면', '시간표 보기', '교과목 삭제하기');
				messageQR($send, $todayDailysUserkey[$i], 'UPDATE');
				
				unset($userInfo4User, $eventInfo4User, $send);
			}
		}
	}
}
/*
if(!in_array($today, $yearsSchedule['dayoff'])) {
	// 계절학기 전체기간
	$termOfSeason = (($today >= $semesterW['start'] && $today <= $semesterW['end']) || ($today >= $semesterS['start'] && $today <= $semesterS['end']));
	// 정규학기 전체기간
	$termOfRegular = (($today >= $semester1['start'] && $today <= $semester1['end']) || ($today >= $semester2['start'] && $today <= $semester2['end']));
	// 정규학기 ((개강일 ~ 중간고사 시작일) && (중간고사 종료일 ~ 기말고사 시작일))
	$termOfRegularFromStartToEnd = ((($today >= $semester1['start'] && $today < $semester1['term']['mid']['start']) || ($today > $semester1['term']['mid']['end'] && $today < $semester1['term']['final']['start'])) || (($today >= $semester2['start'] && $today < $semester2['term']['mid']['start']) || ($today > $semester2['term']['mid']['end'] && $today < $semester2['term']['final']['start'])));
	
	if($termOfSeason || ($termOfRegular && $termOfRegularFromStartToEnd)) {
		////////////////////////////////////////////////////////////////////////////// 수업 종료 후 알림 //////////////////////////////////////////////////////////////////////////////////
		for($i=0; $i<count($userInfo); $i++) {
			// 이벤트 목록에서 휴강으로 등록한 목록이 있는지 체크
			$query = "SELECT * FROM event WHERE userkey='".$userInfo[$i]['userkey']."' AND type='cancel' AND title='".$userInfo[$i]['title']."'";
			$sql4event = $conn->query($query);
			while($row4event = $sql4event->fetch_assoc()) {
				$eventCancel[] = $row4event;
			}
			for($e=0; $e<count($eventCancel); $e++) {
				$eventCancel1 = date("Y-m-d", mktime(0,0,0, substr($eventCancel[$e]['date1'],0,2), substr($eventCancel[$e]['date1'],2,4), date("Y")));
				if($eventCancel[$e]['date2']) {
					$eventCancel2 = date("Y-m-d", mktime(0,0,0, substr($eventCancel[$e]['date2'],0,2), substr($eventCancel[$e]['date2'],2,4), date("Y")));
					if($today >= $eventCancel1 && $today <= $eventCancel2) {
						$eventCancelResult[] = FALSE;
					} else {
						$eventCancelResult[] = TRUE;
					}
				} else {
					if($today == $eventCancel1) {
						$eventCancelResult[] = FALSE;
					} else {
						$eventCancelResult[] = TRUE;
					}
				}
			}
			
			if(in_array(FALSE, $eventCancelResult)) {
				continue;
			} else {
				$daily = array('일', '월', '화', '수', '목', '금', '토');
				$numOfDays = count($daily)-1;
				$date = date('w');
				$todayDaily = $daily[$date];
				
				for($j=1; $j<=$numOfDays; $j++) {
					${finTime.$j} = strtotime($userInfo[$i]['time'.$j]) + ($userInfo[$i]['min'.$j] * 60);
					// 요일 체크
					if($userInfo[$i]['day'.$j] == $todayDaily) {
						// 푸시 시간 체크 (수업 종료 후 10분 후)
						if($now == ${finTime.$j}+(60*10)) {
							$userName = findUserName($userInfo[$i]['userkey']);
							
							$send['text'] = "🎩: " . $userName . "님!\n오늘 " . $userInfo[$i]['title'] . " 수업에 과제∙휴강∙시험은 없었나요?";
							message($send, $userInfo[$i]['userkey']);
							
							ForAlarm($userInfo[$i]['userkey']);
						}
					}
				}		
			}
		}
	}
}*/