<?php header("Content-Type:text/html;charset=utf-8"); ?>
<?php
  require __DIR__ . '/../vendor/autoload.php';
  use Firebase\JWT\JWT;
  use Firebase\JWT\Key;

  // 토큰 header 양식 [authorization] => Bearer eyJ0eXAiOi...
  $headers = apache_request_headers();
  // 테스트 할 땐 아래 한 줄 주석을 풀고 하면 됩니다!
  //$headers['authorization'] = "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vcGxhbml0MjAyMi5jYWZlMjQuY29tIiwiYXVkIjoicGxhbml0QXBwIiwidXNlcklEIjoicGxhbml0VGVzdDEiLCJ1c2VyTmFtZSI6Ilx1ZDUwY1x1Yjc5OFx1YjJkYlx1YjllODEifQ.1uWb9NRO2BAdEPn-V9CqGvpCvLYN6G7BnsD6HO-xH30";
  $authHeader = isset($headers['authorization']) ? $headers['authorization'] : null;

  //최종 출력할 결과
  $outputFrame = new stdClass;
  $outputFrame->result = 0; //오류 코드: 0 -> 정상, 나머지 오류는 임의배정
  $outputFrame->message = "success"; //오류 메세지: 각 오류 상황에서 재설정

  //토큰이 존재할 경우
  if (isset($authHeader)) {
    $splitAuthHeader = explode(" ", $authHeader);
    $jwt = $splitAuthHeader[1];
    try {
      $secretkey = '------';
      $decoded = JWT::decode($jwt, new Key($secretkey, 'HS256'));
      $decodedJwt = (array) $decoded;
      //valid 한 경우
      $userID = $decodedJwt['userID'];

      /* post 파라미터 받는 부분 */
      $plantID = $_POST['plantID'];
      $plantType = $_POST['plantType'];
      /* ======================== */

      /* DB연결하는 부분 */
      $host = 'localhost';
      $user = 'planit2022';
      $password = '------';
      $dbName = 'planit2022';

      header('Content-Type: application/json');

      $conn = new mysqli($host, $user, $password, $dbName);
      if (!($conn)) {
        echo "db 연결 실패: " . mysqli_connect_error();
      }
      /* ======================== */

      $stmt = mysqli_init();

      $sql = "SELECT Growth FROM PLANT WHERE PlantID = ?;";   // 입력받은 checkID에 해당하는 체크아이템의 UserID
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i",$plantID);
      $stmt->execute();
      $result = $stmt->get_result();

      if(mysqli_num_rows($result) > 0){ // 쿼리 결과로 1행 이상 존재한다면
        $row = mysqli_fetch_assoc($result);
        if ($row['Growth'] <= 500) { //1단계 식물에 한해서 타입 업데이트
            $resJsonString = file_get_contents('json/plantInfo.json');
            $datas = json_decode($resJsonString, true);
            $resArr = $datas[$plantType];
            $res1 = rand(0, count($resArr)-1);
            
            $isSingle = $resArr[$res1]["isSingle"];
            $res2 = null;
            if(!$isSingle) {
                $isSingle2 = true;
                while($isSingle2) {
                    $res2 = rand(0, count($resArr)-1);
                    $isSingle2 = $resArr[$res2]["isSingle"];
                }
            }
            
            $sql = "UPDATE PLANT
                    SET Type = ?, Resource1 = ?, Resource2 = ?, IsSingle = ?
                    WHERE PlantID = ?;";
            $stmt = $conn->prepare($sql);
            $isSingleInt = $isSingle?1:0;
            $stmt->bind_param("iiiii",$plantType,$res1,$res2,$isSingleInt,$plantID);
            $result = $stmt->execute();

            if ($result === true) {
              //성공 시 동작
              $outputFrame->data = 1;
            }
          }
          else {
              //2단계 이상의 식물인 경우
              $outputFrame->result = 4;
              $outputFrame->message = "식물이 2단계 이상입니다.";
          }
      } else {
        // 결과 없으면
        $outputFrame->result = 3;
        $outputFrame->message = "존재하지 않는 식물 입니다.";
      }

      /* */
      $stmt->close();
      mysqli_close($conn);

    } catch (\Exception $e) { // invalid한 경우
      $outputFrame->result = 2;
      $outputFrame->message = "유효하지 않은 토큰입니다.";
    }
  } else { //토큰이 없는 경우
    $outputFrame->result = 1;
    $outputFrame->message = "토큰이 누락되었습니다.";
  }
  echo json_encode($outputFrame);
?>
