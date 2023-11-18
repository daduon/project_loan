<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Borrow_Master;
use App\Models\Borrow_Schedule;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Haruncpi\LaravelIdGenerator\IdGenerator;

class BorrowMasterController extends Controller
{
    public function retrieveListBorrower()
    {
        $borrow_masters = DB::table('customers')
            ->orderBy('customer_id', 'asc')
            ->join('borrow_masters', 'customers.id', '=', 'borrow_masters.customer_id')
            ->select('*')
            ->get();
           
        
        return $borrow_masters;
    }

    public function createBorrower(Request $request, Borrow_Master $borrow_Master)
    {

        $id = IdGenerator::generate(['table' => 'borrow_masters', 'length' => 14, 'prefix' => 'BRW-']);
    
        $borrowingNo                                = $id;
        $borrowingInterestPaymentTypeCode           = $request->borrowingTypeCode;
        $borrowingPrinciplePaymentTypeCode          = $request->borrowingPrinciplePaymentTypeCode;
        $maturityDate                               = $request->maturityDate;
        $newDate                                    = $request->newDate;
        $firstInterestPaymentDate                   = $request->firstInterestPaymentDate;
        $taxRate                                    = $request->taxRate;
        $newTaxRate                                 = $request->newTaxRate;
        $newTaxRateFromDate                         = $request->newTaxRateFromDate;
        $newTaxRateToDate                           = $request->newTaxRateToDate;;
        $loanAmount                                 = $request->loanAmount;
        $currencyCode                               = $request->currencyCode;;
        $applyInterestRate                          = $request->applyInterestRate;
        $borrowingInterestCalculationTypeCode       = $request->borrowingInterestCalculationTypeCode;
        $payType                                    = $request->payType;
        if($payType !=='15') {
            $results = DB::select("

            WITH /* K.vichet / this is schedule detail for insert into table schedule */
                 BorrowingInterestPaymentTypeCode AS (SELECT CASE '$borrowingPrinciplePaymentTypeCode' WHEN '01' THEN 1 WHEN '02' THEN 3 WHEN '03' THEN 6 WHEN '04' THEN 12 WHEN '05' THEN 9999 ELSE 0 END borrowingInterestPaymentTypeCode) --Make Code as month count
                , BorrowingPrinciplePaymentTypeCode AS (SELECT CASE '$borrowingPrinciplePaymentTypeCode' WHEN '01' THEN 1 WHEN '02' THEN 3 WHEN '03' THEN 6 WHEN '04' THEN 12 WHEN '05' THEN 9999 ELSE 0 END borrowingPrinciplePaymentTypeCode)--Make Code as month count
                , maturityDate 		AS ( select '$maturityDate' 		maturityDate ) 		--Define for using
                , newDate 			AS ( select '$newDate'				newDate ) 			--Define for using
                , firstPaymentDate 	AS ( select '$firstInterestPaymentDate' 	firstPaymentDate )	--Define for using
                , allDate AS ( --Merge date param as one row
                    SELECT * FROM newDate
                    CROSS JOIN firstPaymentDate
                    CROSS JOIN maturityDate
                )
                , InterestSchedule AS (
                    WITH RECURSIVE InterestSchedule(n, o, p) as
                    ( SELECT firstPaymentDate n, 1 o, firstPaymentDate p from firstPaymentDate
                        UNION ALL
                        SELECT TO_CHAR( n::DATE + CAST((borrowingInterestPaymentTypeCode)||' '||'MONTH' AS INTERVAL), 'yyyymmdd'), o + 1, TO_CHAR('$firstInterestPaymentDate'::DATE + CAST((borrowingInterestPaymentTypeCode * o)||' '||'MONTH' AS INTERVAL), 'yyyymmdd')
                        FROM InterestSchedule n CROSS JOIN BorrowingInterestPaymentTypeCode
                        WHERE n.n < (select maturityDate FROM maturityDate)
                    )select * from InterestSchedule
                )
                , InterestSchedule1 as (select
                    *
                    -- if first payment is EOM then next paymentdate is EOM
                    , case when firstPaymentDate != TO_CHAR((date_trunc('month', firstPaymentDate::date) + interval '1 month' - interval '1 day')::date, 'yyyymmdd') then p else TO_CHAR((date_trunc('month', p::date) + interval '1 month' - interval '1 day')::date, 'yyyymmdd') end p1
                from
                    InterestSchedule cross join firstPaymentDate
                )
                , b as ( --Merge New Date, Schedule Date and Maturity Date
                    SELECT newDate dates from newDate
                    UNION
                    SELECT p1 FROM InterestSchedule1
                    UNION
                    SELECT maturityDate from maturityDate
                )
                , c AS ( --Move to next biz date
                    SELECT
                            b.dates nextBizDay
                            , *
                    FROM
                        B CROSS JOIN allDate
                    WHERE dates <= maturityDate
                    ORDER BY
                        dates
                )
                , d as ( --Filter out the date after maturity date
                    SELECT
                            CASE WHEN nextBizDay = newdate THEN newdate ELSE nextBizDay END fromDate
                            , LEAD (nextBizDay,1) OVER (ORDER BY nextBizDay) AS toDate
                            , *
                            , ROW_NUMBER() over() monthCount
                    FROM
                        c
                    WHERE dates <= maturityDate
                    ORDER BY
                        dates
                )
                , e as ( -- If days in last records of schedule is less than 15 days it will remove one month
                    SELECT
                        *
                        , todate::DATE - fromDate::DATE  dayCount
                        , case
                            when todate = maturitydate and (todate::DATE - fromDate::DATE) < 15 THEN monthcount - 1
                        else monthcount end monthcount1
                    FROM
                        d
                    where toDate IS NOT NULL
                )
                ,f as ( --Merge the month that is less than 15 days
                    SELECT
                        MIN(fromdate) fromdate,
                        MAX(todate) todate,
                        MAX(nextbizday) nextbizday,
                        MAX(dates) dates,
                        MAX(newdate) newdate,
                        MAX(firstpaymentdate) firstpaymentdate,
                        MAX(maturitydate) maturitydate,
                        MIN(monthcount) monthcount,
                        SUM(daycount) daycount
                    FROM
                        e
                    group by monthcount1
                    order by monthcount
                )
                , g as ( --Calculate monthN for Pricipal Pay
                    SELECT
                        *
                        , CEIL(monthcount*borrowinginterestpaymenttypecode/borrowingprinciplepaymenttypecode::NUMERIC)  AS monthN
                        , ROW_NUMBER () OVER (PARTITION BY CEIL(monthcount*borrowinginterestpaymenttypecode/borrowingprinciplepaymenttypecode::NUMERIC)) monthNCount
                        , max(monthCount) OVER () as maxMonthCount
                    FROM f CROSS JOIN BorrowingInterestPaymentTypeCode CROSS JOIN BorrowingPrinciplePaymentTypeCode
                )
                , H AS (
                    SELECT
                        *
                        , CASE
                            WHEN
                                    (borrowinginterestpaymenttypecode = borrowingprinciplepaymenttypecode)
                                OR
                                    (monthNCount * borrowinginterestpaymenttypecode =  borrowingprinciplepaymenttypecode) --Interval
                                OR
                                    (maxMonthCount = monthcount) --Last Row
                                    THEN TRUE
                            ELSE
                                FALSE
                            END  isPrinciplePayment
                    FROM
                        G
                )
                , Months AS (
                    SELECT *
                        , SUM(CASE WHEN isprinciplepayment THEN 1 ELSE 0 END) OVER() AS principlePaymentCount
                    FROM H
                )
                , Days AS (
                    SELECT
                        TO_CHAR(days, 'yyyymmdd') days,
                        1 as dayCount,
                        $taxRate taxRate,
                        $newTaxRate newTaxRate,
                        '$newTaxRateFromDate' newTaxRateFromDate,
                        TO_CHAR(NULLIF('$newTaxRateToDate','')::DATE - interval '1 day', 'yyyymmdd') newTaxRateToDate,
                        $loanAmount loanBalance,
                        '$currencyCode' currencyCode,
                        $applyInterestRate applyInterestRate,
                        CASE '$borrowingInterestCalculationTypeCode'
                            WHEN '01' THEN 360
                            WHEN '02' THEN 365
                            WHEN '03' THEN (LEFT(TO_CHAR(days, 'yyyymmdd'), 4) || '1231')::DATE - (LEFT(TO_CHAR(days, 'yyyymmdd'), 4) || '0101')::DATE   + 1
                            ELSE 0
                        END dayCountPerYear
                    FROM generate_series(
                        '$newDate':: DATE,
                        '$maturityDate':: DATE,
                        '1 day'
                    ) AS days CROSS JOIN allDate
                )
                , MERG_DAY_MONTH AS (
                    SELECT
                        A.*
                        , B.toDate paymentDate
                        , B.isPrinciplePayment
                        , B.maturitydate
                        , B.fromDate
                        , B.toDate
                        , B.monthcount
                        , COALESCE(CASE WHEN B.isprinciplepayment THEN
                                CASE
                                    WHEN A.currencyCode = 'USD' THEN TRUNC(A.loanbalance::NUMERIC  / B.principlePaymentCount, 2)
                                    WHEN A.currencyCode = 'KHR' THEN TRUNC(A.loanbalance::NUMERIC  / B.principlePaymentCount)
                                    ELSE 0
                                END
                            ELSE 0 END, 0) principlePaymentAmount
                    FROM
                        Days A
                            LEFT JOIN Months B ON A.days = B.todate
                )
                , CalculateLoanBalance AS (
                    SELECT
                        *
                        , SUM(principlepaymentamount) over (order by days asc rows between unbounded preceding and current row) accumulateSubstractAmount
                        , loanbalance - SUM(principlepaymentamount) over (order by days asc rows between unbounded preceding and current row) loanbalance1
                    FROM
                        MERG_DAY_MONTH
                )
                , CalculateLoanBalance1 AS (
                    SELECT
                        *
                        , CASE WHEN days = maturitydate THEN principlepaymentamount + loanbalance1 ELSE principlepaymentamount END principlepaymentamount1
                        , CASE WHEN days = maturitydate THEN loanbalance - (accumulatesubstractamount + loanbalance1) ELSE loanbalance1 END loanbalance2
                        , CASE WHEN days between newtaxratefromdate and newtaxratetodate THEN newtaxrate ELSE taxrate END newTaxRate1
                    FROM
                    CalculateLoanBalance
                )
                , CalculateLoanBalance2 AS (
                    SELECT
                        *
                        , ( loanbalance2  * applyinterestrate / 100) / dayCountPerYear dailyInterestAmount --Calculate Interest before calculate loanBalance
                        , (( loanbalance2  * applyinterestrate / 100) / dayCountPerYear) * newTaxRate1 / 100 taxAmount --Calculate tax before calculate loanBalance
                        , CASE WHEN days = maturitydate THEN paymentdate ELSE (SELECT toDate FROM Months AA where A.days <= AA.toDate ORDER BY toDate Limit 1) END paymentdate2
                        , CASE WHEN days between newtaxratefromdate	AND newtaxratetodate THEN 'New withholding tax ' || TO_CHAR(newTaxRate1, 'FM990.90') || '% start from ' || TO_CHAR(newtaxratefromdate::DATE, 'dd/mm/yyyy') || ' to ' || TO_CHAR(newtaxratetodate::DATE + interval '1 day','dd/mm/yyyy')	ELSE '' END			remark
                        , CASE WHEN days between newtaxratefromdate	AND newtaxratetodate THEN newTaxRate ELSE taxrate END		taxrate1
                    FROM
                        CalculateLoanBalance1 A
                )
                , CalculateLoanBalance22 as (
                    select --not include first day
                        *
                        , daycount daycount2
                        , dailyinterestamount dailyinterestamount2
                        , taxamount taxamount2
                        , LEAD(paymentdate2) OVER (ORDER BY days)  paymentdate3
                        , LEAD(paymentdate) OVER (ORDER BY days)  paymentdate4
                        , LEAD(days) OVER (ORDER BY days)  days1
                        , LEAD(isprinciplepayment) OVER (ORDER BY days)  isprinciplepayment1
                        , LEAD(fromdate) OVER (ORDER BY days) fromdate2
                        , LEAD(todate) OVER (ORDER BY days) todate2
                        , LEAD(monthcount) OVER (ORDER BY days) monthcount2
                        , LEAD(loanbalance2) OVER (ORDER BY days) loanbalance3
                        , LEAD(principlepaymentamount1) OVER (ORDER BY days) principlepaymentamount2
                    from
                        CalculateLoanBalance2
                    ORDER BY days
                )
                , CalculateLoanBalance3 AS (
                    SELECT
                        paymentdate3
                        , MAX(monthcount2)	monthcount
                        , SUM(daycount2) daycount
                        , MAX(taxrate1)	taxrate1
                        , MAX(applyinterestrate)	applyinterestrate
                        , MAX(fromdate2)				fromDate
                        , MAX(toDate2)				toDate
                        , SUM(CASE WHEN paymentdate4 = days1 AND isprinciplepayment1 THEN principlepaymentamount2 ELSE 0 END)				principlepaymentamount1
                        , MIN(loanbalance3)			loanbalance
                        , SUM(dailyinterestamount2)	dailyinterestamount
                        , SUM(taxamount2)			taxamount
                        , MAX(newtaxratefromdate)	newtaxratefromdate
                        , MAX(newtaxratetodate)		newtaxratetodate
                        , MAX(loanbalance)			loanAmount
                        , MAX(currencycode)			currencycode
                        , MAX(remark)				remark
                        , MAX(dayCountPerYear)		dayCountPerYear
                    FROM
                        CalculateLoanBalance22
                    GROUP BY paymentdate3
                    ORDER BY paymentdate3
                )
                , CalculateLoanBalance4 AS (
                    SELECT
                        *
                        , CASE WHEN currencycode = 'USD' THEN TRUNC(ROUND(dailyinterestamount, 10), 2) ELSE ROUND(dailyinterestamount) END dailyinterestamount1
                        , CASE WHEN currencycode = 'USD' THEN TRUNC(ROUND(taxamount,10), 2) ELSE ROUND(taxamount) END taxamount1
                    FROM
                        CalculateLoanBalance3
                    WHERE paymentdate3 IS NOT null
                )
                SELECT
                       '$borrowingNo'   								AS borrowingNo
                    , paymentdate3 									AS paymentApplyDate
                    , monthcount 									AS paymentNumberOfTime
                    , applyinterestrate 							AS applyInterestRate
                    , taxrate1 										AS taxRate
                    , loanbalance 									AS loanBalance
                    , fromdate 										AS scheduleStartDate
                    , todate 										AS scheduleEndDate
                    , daycount 										AS calcDays
                    , principlepaymentamount1 						AS repayPrincipal
                    , dailyinterestamount1 							AS repayInterest
                    , taxamount1 									AS taxAmount
                    , '01' 						AS ledgerStatusCode
                    , remark 										AS remark
                FROM
                    CalculateLoanBalance4
            ");

            foreach ($results as $key) {
                $insertSchedule = new Borrow_Schedule;

                $insertSchedule->id                             = $Sedid = IdGenerator::generate(['table' => 'borrow_schedules', 'length' => 14, 'prefix' => 'SED-']);
                $insertSchedule->borrowing_id                   = $id;
                $insertSchedule->paymentapplydate               = $key->paymentapplydate;
                $insertSchedule->taxamount                      = $key->taxamount;
                $insertSchedule->repaytaxamount                 = $key->taxamount;
                $insertSchedule->repayinterest                  = $key->repayinterest;
                $insertSchedule->ledgerstatuscode               = $key->ledgerstatuscode;
                $insertSchedule->paymentfromdate                = $key->schedulestartdate;
                $insertSchedule->paymenttodate                  = $key->scheduleenddate;
                $insertSchedule->applyinterestrate              = $key->applyinterestrate;
                $insertSchedule->scheduleseqno                  = $key->paymentnumberoftime;
                $insertSchedule->schedulestatuscode             = 0;
                $insertSchedule->transactionprincipal           = $key->loanbalance;
                $insertSchedule->transactioninterestamount      = $key->repayinterest;
                $insertSchedule->repayprincipal                 = $key->repayprincipal;
                $insertSchedule->calc                           = $key->calcdays;
                $insertSchedule->save();
            }
        }

       else {
            $borrowingPrinciplePaymentTypeCode ='01';
            $borrowingInterestPaymentTypeCode ='01';
            $results = DB::select("
               
                                WITH /* K.vichet / this is schedule detail for insert into table schedule */
                                BorrowingInterestPaymentTypeCode AS (SELECT CASE '$borrowingInterestPaymentTypeCode' WHEN '01' THEN 1  ELSE 0 END borrowingInterestPaymentTypeCode) --Make Code as month count
                                , BorrowingPrinciplePaymentTypeCode AS (SELECT CASE '$borrowingPrinciplePaymentTypeCode' WHEN '01' THEN 1 ELSE 0 END borrowingPrinciplePaymentTypeCode)--Make Code as month count
                                , maturityDate 		AS ( select '$maturityDate' 		maturityDate ) 		--Define for using
                                , newDate 			AS ( select '$newDate' 				newDate ) 			--Define for using
                                , firstPaymentDate 	AS ( select '$firstInterestPaymentDate' 	firstPaymentDate )	--Define for using
                                , allDate AS ( --Merge date param as one row
                                SELECT * FROM newDate
                                CROSS JOIN firstPaymentDate
                                CROSS JOIN maturityDate
                                )
                                , InterestSchedule AS ( 
                                WITH RECURSIVE InterestSchedule(n, o, p) as
                                ( SELECT firstPaymentDate n, 0.5 o, firstPaymentDate p from firstPaymentDate
                                    UNION ALL
                                    SELECT TO_CHAR(n::DATE + CAST((borrowingInterestPaymentTypeCode)||' '||'MONTH' AS INTERVAL), 'yyyymmdd'), o + 0.5, TO_CHAR('$firstInterestPaymentDate'::DATE + CAST((borrowingInterestPaymentTypeCode * o)||' '||'MONTH' AS INTERVAL), 'yyyymmdd') 
                                    FROM InterestSchedule n CROSS JOIN BorrowingInterestPaymentTypeCode
                                    WHERE n.p < (select maturityDate FROM maturityDate)
                                )select * from InterestSchedule
                                )
                                --			select * FROM InterestSchedule
                                --			
                                , InterestSchedule1 as (select
                                *
                                -- if first payment is EOM then next paymentdate is EOM
                                , case when firstPaymentDate != TO_CHAR((date_trunc('month', firstPaymentDate::date) + interval '1 month' - interval '1 day')::date, 'yyyymmdd') then p else TO_CHAR((date_trunc('month', p::date) + interval '1 month' - interval '1 day')::date, 'yyyymmdd') end p1
                                from
                                InterestSchedule cross join firstPaymentDate
                                )
                                , b as ( --Merge New Date, Schedule Date and Maturity Date
                                SELECT newDate dates from newDate
                                UNION
                                SELECT p1 FROM InterestSchedule1
                                UNION
                                SELECT maturityDate from maturityDate
                                )
                                , c AS ( --Move to next biz date
                                SELECT
                                        b.dates nextBizDay
                                        , *
                                FROM
                                    B CROSS JOIN allDate
                                WHERE dates <= maturityDate
                                ORDER BY
                                    dates
                                )
                                , d as ( --Filter out the date after maturity date
                                SELECT
                                        CASE WHEN nextBizDay = newdate THEN newdate ELSE nextBizDay END fromDate
                                        , LEAD (nextBizDay,1) OVER (ORDER BY nextBizDay) AS toDate
                                        , *
                                        , ROW_NUMBER() over() monthCount
                                FROM
                                    c
                                WHERE dates <= maturityDate
                                ORDER BY
                                    dates
                                )
                                , e as ( -- If days in last records of schedule is less than 15 days it will remove one month
                                SELECT
                                    *
                                    , todate::DATE - fromDate::DATE  dayCount
                                    , case 
                                        when todate = maturitydate and (todate::DATE - fromDate::DATE) < 15 THEN monthcount - 1 
                                    else monthcount end monthcount1
                                FROM
                                    d
                                where toDate IS NOT NULL
                                )
                                ,f as ( --Merge the month that is less than 15 days
                                SELECT
                                    MIN(fromdate) fromdate,
                                    MAX(todate) todate,
                                    MAX(nextbizday) nextbizday,
                                    MAX(dates) dates,
                                    MAX(newdate) newdate,
                                    MAX(firstpaymentdate) firstpaymentdate,
                                    MAX(maturitydate) maturitydate,
                                    MIN(monthcount) monthcount,
                                    SUM(daycount) daycount
                                FROM
                                    e 
                                group by monthcount1
                                order by monthcount
                                )
                                , g as ( --Calculate monthN for Pricipal Pay
                                SELECT														
                                    *
                                    , CEIL(monthcount*borrowinginterestpaymenttypecode/borrowingprinciplepaymenttypecode::NUMERIC)  AS monthN
                                    , ROW_NUMBER () OVER (PARTITION BY CEIL(monthcount*borrowinginterestpaymenttypecode/borrowingprinciplepaymenttypecode::NUMERIC)) monthNCount
                                    , max(monthCount) OVER () as maxMonthCount
                                FROM f CROSS JOIN BorrowingInterestPaymentTypeCode CROSS JOIN BorrowingPrinciplePaymentTypeCode
                                )
                                , H AS (
                                SELECT
                                    *
                                    , CASE 
                                        WHEN 
                                                (borrowinginterestpaymenttypecode = borrowingprinciplepaymenttypecode) 
                                            OR
                                                (monthNCount * borrowinginterestpaymenttypecode =  borrowingprinciplepaymenttypecode) --Interval
                                            OR
                                                (maxMonthCount = monthcount) --Last Row
                                                THEN TRUE 
                                        ELSE 
                                            FALSE 
                                        END  isPrinciplePayment
                                FROM
                                    G
                                )
                                , Months AS (
                                SELECT * 
                                    , SUM(CASE WHEN isprinciplepayment THEN 1 ELSE 0 END) OVER() AS principlePaymentCount
                                FROM H
                                )
                                --			select * FROM Months
                                --			
                                , Days AS (
                                SELECT 
                                    TO_CHAR(days, 'yyyymmdd') days,
                                    1 as dayCount,
                                    $taxRate taxRate,
                                    $newTaxRate newTaxRate,
                                    '$newTaxRateFromDate' newTaxRateFromDate,
                                    TO_CHAR(NULLIF('$newTaxRateToDate','')::DATE - interval '1 day', 'yyyymmdd') newTaxRateToDate,
                                    $loanAmount loanBalance,
                                    '$currencyCode' currencyCode,
                                    $applyInterestRate applyInterestRate,
                                    CASE '$borrowingInterestCalculationTypeCode' 
                                        WHEN '01' THEN 360 
                                        WHEN '02' THEN 365 
                                        WHEN '03' THEN (LEFT(TO_CHAR(days, 'yyyymmdd'), 4) || '1231')::DATE - (LEFT(TO_CHAR(days, 'yyyymmdd'), 4) || '0101')::DATE   + 1
                                        ELSE 0 
                                    END dayCountPerYear
                                FROM generate_series(
                                    '$newDate' :: DATE,
                                    '$maturityDate' :: DATE,
                                    '1 day'
                                ) AS days CROSS JOIN allDate
                                )
                                , MERG_DAY_MONTH AS (
                                SELECT 
                                    A.*
                                    , B.toDate paymentDate
                                    , B.isPrinciplePayment
                                    , B.maturitydate
                                    , B.fromDate
                                    , B.toDate
                                    , B.monthcount
                                    , COALESCE(CASE WHEN B.isprinciplepayment THEN 
                                            CASE 
                                                WHEN A.currencyCode = 'USD' THEN TRUNC(A.loanbalance::NUMERIC  / B.principlePaymentCount, 2) 
                                                WHEN A.currencyCode = 'KHR' THEN TRUNC(A.loanbalance::NUMERIC  / B.principlePaymentCount) 
                                                ELSE 0 
                                            END 
                                        ELSE 0 END, 0) principlePaymentAmount
                                FROM
                                    Days A 
                                        LEFT JOIN Months B ON A.days = B.todate
                                )
                                --			SELECT * FROM MERG_DAY_MONTH
                                --			
                                , CalculateLoanBalance AS ( 
                                SELECT
                                    *
                                    , SUM(principlepaymentamount) over (order by days asc rows between unbounded preceding and current row) accumulateSubstractAmount
                                    , loanbalance - SUM(principlepaymentamount) over (order by days asc rows between unbounded preceding and current row) loanbalance1
                                FROM
                                    MERG_DAY_MONTH
                                )
                                , CalculateLoanBalance1 AS (
                                SELECT
                                    *
                                    , CASE WHEN days = maturitydate THEN principlepaymentamount + loanbalance1 ELSE principlepaymentamount END principlepaymentamount1
                                    , CASE WHEN days = maturitydate THEN loanbalance - (accumulatesubstractamount + loanbalance1) ELSE loanbalance1 END loanbalance2
                                    , CASE WHEN days between newtaxratefromdate and newtaxratetodate THEN newtaxrate ELSE taxrate END newTaxRate1
                                FROM
                                CalculateLoanBalance
                                )
                                , CalculateLoanBalance2 AS (
                                SELECT
                                    *
                                    , ( loanbalance2  * applyinterestrate / 100) / dayCountPerYear dailyInterestAmount --Calculate Interest before calculate loanBalance
                                    , (( loanbalance2  * applyinterestrate / 100) / dayCountPerYear) * newTaxRate1 / 100 taxAmount --Calculate tax before calculate loanBalance
                                    , CASE WHEN days = maturitydate THEN paymentdate ELSE (SELECT toDate FROM Months AA where A.days <= AA.toDate ORDER BY toDate Limit 1) END paymentdate2
                                    , CASE WHEN days between newtaxratefromdate	AND newtaxratetodate THEN 'New withholding tax ' || TO_CHAR(newTaxRate1, 'FM990.90') || '% start from ' || TO_CHAR(newtaxratefromdate::DATE, 'dd/mm/yyyy') || ' to ' || TO_CHAR(newtaxratetodate::DATE + interval '1 day','dd/mm/yyyy')	ELSE '' END			remark
                                    , CASE WHEN days between newtaxratefromdate	AND newtaxratetodate THEN newTaxRate ELSE taxrate END		taxrate1
                                FROM
                                    CalculateLoanBalance1 A
                                )
                                , CalculateLoanBalance22 as (
                                select --not include first day
                                    *
                                    , daycount daycount2
                                    , dailyinterestamount dailyinterestamount2
                                    , taxamount taxamount2
                                    , LEAD(paymentdate2) OVER (ORDER BY days)  paymentdate3
                                    , LEAD(paymentdate) OVER (ORDER BY days)  paymentdate4
                                    , LEAD(days) OVER (ORDER BY days)  days1
                                    , LEAD(isprinciplepayment) OVER (ORDER BY days)  isprinciplepayment1
                                    , LEAD(fromdate) OVER (ORDER BY days) fromdate2
                                    , LEAD(todate) OVER (ORDER BY days) todate2
                                    , LEAD(monthcount) OVER (ORDER BY days) monthcount2
                                    , LEAD(loanbalance2) OVER (ORDER BY days) loanbalance3
                                    , LEAD(principlepaymentamount1) OVER (ORDER BY days) principlepaymentamount2
                                from
                                    CalculateLoanBalance2
                                ORDER BY days
                                )
                                , CalculateLoanBalance3 AS (
                                SELECT
                                    paymentdate3
                                    , MAX(monthcount2)	monthcount
                                    , SUM(daycount2) daycount
                                    , MAX(taxrate1)	taxrate1
                                    , MAX(applyinterestrate)	applyinterestrate
                                    , MAX(fromdate2)				fromDate
                                    , MAX(toDate2)				toDate
                                    , SUM(CASE WHEN paymentdate4 = days1 AND isprinciplepayment1 THEN principlepaymentamount2 ELSE 0 END)				principlepaymentamount1
                                    , MIN(loanbalance3)			loanbalance
                                    , SUM(dailyinterestamount2)	dailyinterestamount
                                    , SUM(taxamount2)			taxamount
                                    , MAX(newtaxratefromdate)	newtaxratefromdate
                                    , MAX(newtaxratetodate)		newtaxratetodate
                                    , MAX(loanbalance)			loanAmount
                                    , MAX(currencycode)			currencycode
                                    , MAX(remark)				remark
                                    , MAX(dayCountPerYear)		dayCountPerYear
                                FROM
                                    CalculateLoanBalance22
                                GROUP BY paymentdate3
                                ORDER BY paymentdate3
                                )
                                , CalculateLoanBalance4 AS (
                                SELECT
                                    *
                                    , CASE WHEN currencycode = 'USD' THEN TRUNC(ROUND(dailyinterestamount, 10), 2) ELSE ROUND(dailyinterestamount) END dailyinterestamount1
                                    , CASE WHEN currencycode = 'USD' THEN TRUNC(ROUND(taxamount,10), 2) ELSE ROUND(taxamount) END taxamount1
                                FROM
                                    CalculateLoanBalance3
                                WHERE paymentdate3 IS NOT null
                                )
                                SELECT
                                '$borrowingNo'									AS borrowingNo
                                , paymentdate3 									AS paymentApplyDate
                                , monthcount 									AS paymentNumberOfTime
                                , applyinterestrate 							AS applyInterestRate
                                , taxrate1 										AS taxRate
                                , loanbalance 									AS loanBalance
                                , fromdate 										AS scheduleStartDate 
                                , todate 										AS scheduleEndDate
                                , daycount 										AS calcDays
                                , principlepaymentamount1 						AS repayPrincipal
                                , dailyinterestamount1 							AS repayInterest
                                , taxamount1 									AS taxAmount
                                , '01' 											AS ledgerStatusCode
                                , remark 										AS remark
                                FROM
                                CalculateLoanBalance4
            ");
            foreach ($results as $key) {
                $insertSchedule = new Borrow_Schedule;

                $insertSchedule->id                             = $Sedid = IdGenerator::generate(['table' => 'borrow_schedules', 'length' => 14, 'prefix' => 'SED-']);
                $insertSchedule->borrowing_id                   = $id;
                $insertSchedule->paymentapplydate               = $key->paymentapplydate;
                $insertSchedule->taxamount                      = $key->taxamount;
                $insertSchedule->repaytaxamount                 = $key->taxamount;
                $insertSchedule->repayinterest                  = $key->repayinterest;
                $insertSchedule->ledgerstatuscode               = $key->ledgerstatuscode;
                $insertSchedule->paymentfromdate                = $key->schedulestartdate;
                $insertSchedule->paymenttodate                  = $key->scheduleenddate;
                $insertSchedule->applyinterestrate              = $key->applyinterestrate;
                $insertSchedule->scheduleseqno                  = $key->paymentnumberoftime;
                $insertSchedule->schedulestatuscode             = 0;
                $insertSchedule->transactionprincipal           = $key->loanbalance;
                $insertSchedule->transactioninterestamount      = $key->repayinterest;
                $insertSchedule->repayprincipal                 = $key->repayprincipal;
                $insertSchedule->calc                           = $key->calcdays;
                $insertSchedule->save();
            };
            
        }

        $borrow_Master->id                     =  $id;
        $borrow_Master->customer_id            = $request->customerId;
        $borrow_Master->borrowingtypecode      = $request->borrowingTypeCode;
        $borrow_Master->currencycode           = $request->currencyCode;
        $borrow_Master->loanamount             = $request->loanAmount;
        $borrow_Master->maturitydate           = $request->maturityDate;
        $borrow_Master->startDate              = $request->newDate;
        $borrow_Master->applyinterestrate      = $request->applyInterestRate;
        $borrow_Master->ispaid                 ='0';
        $borrow_Master->payType                =$request->payType;
        $borrow_Master->numofmonth             =$request->numofmonth;
        $borrow_Master->remarkdesc             =$request->remarkDesc;

        $borrow_Master->save();

        return $borrow_Master;
    }

    public function updateBorrower(Request $request, $id)
    {
        $borrow_master = Borrow_Master::findOrFail($id);
        $borrow_master->update($request->all());

        return  $borrow_master;
    }
    public function retrieveBorrower($id)
    {
        $borrow_masters = DB::table('customers')
            ->join('borrow_masters', 'customers.id', '=', 'borrow_masters.customer_id')
            ->where('customers.customer_id', '=', $id)
            ->select('*')
            ->get();

        return $borrow_masters;
    }

    public function retrieveSchedulePayment($customerId,$borrowingId)
    {
        $results = DB::select("
        SELECT
              A.ID  													AS customerId
            , A.CUSTOMER_NAME											AS customerName
            , C.BORROWING_ID											AS borrowingId
            , C.PAYMENTAPPLYDATE 										AS paymentapplyDate
            , C.TAXAMOUNT           									AS taxAmount
            , C.TRANSACTIONINTERESTAMOUNT 								AS transactionInterestAmount
            , C.TRANSACTIONPRINCIPAL 									AS transactionPrincipal
            , C.PAYMENTFROMDATE											AS paymentFromDate
            , C.PAYMENTTODATE 											AS paymentToDate
            , C.SCHEDULESTATUSCODE 										AS scheduleStatusCode
            , C.APPLYINTERESTRATE 										AS applyInterestRate
            , C.REPAYTAXAMOUNT 											AS repayTaxAmount
            , C.REPAYINTEREST											AS repayInterest
            , C.REPAYPRINCIPAL											AS repayPrincipal
            , C.SCHEDULESEQNO											AS scheduleSeqNo
            , C.ID 														AS borrowId
            , B.REMARKDESC												AS remark
            , (C.REPAYTAXAMOUNT + C.REPAYINTEREST + C.REPAYPRINCIPAL) 	AS payment
        FROM CUSTOMERS AS A
        INNER JOIN BORROW_MASTERS 	AS B ON A.ID = B.CUSTOMER_ID
        INNER JOIN BORROW_SCHEDULES AS C ON B.ID = C.BORROWING_ID
        WHERE   A.ID           = '$customerId'
            AND C.BORROWING_ID = '$borrowingId'
        ORDER BY SCHEDULESEQNO ASC");
        return $results;
    }

    public function test () {


        $id = IdGenerator::generate(['table' => 'borrow_masters', 'length' => 14, 'prefix' => 'BRW-']);
    
        $borrowingNo                                = $id;
        $borrowingInterestPaymentTypeCode           = '01';
        $borrowingPrinciplePaymentTypeCode          = '01';
        $maturityDate                               = '20240529';
        $newDate                                    = '20230930';
        $firstInterestPaymentDate                   = '20230930';
        $taxRate                                    = 0;
        $newTaxRate                                 = 0;
        $newTaxRateFromDate                         = '';
        $newTaxRateToDate                           = '';
        $loanAmount                                 = 1000;
        $currencyCode                               = 'USD';
        $applyInterestRate                          = 0;
        $borrowingInterestCalculationTypeCode       = '01';
        $payType                                    = '015';
        $borrowingPrinciplePaymentTypeCode ='01';
        $borrowingInterestPaymentTypeCode ='01';
        $results = DB::select("
           
                            WITH /* K.vichet / this is schedule detail for insert into table schedule */
                            BorrowingInterestPaymentTypeCode AS (SELECT CASE '$borrowingInterestPaymentTypeCode' WHEN '01' THEN 1  ELSE 0 END borrowingInterestPaymentTypeCode) --Make Code as month count
                            , BorrowingPrinciplePaymentTypeCode AS (SELECT CASE '$borrowingPrinciplePaymentTypeCode' WHEN '01' THEN 1 ELSE 0 END borrowingPrinciplePaymentTypeCode)--Make Code as month count
                            , maturityDate 		AS ( select '$maturityDate' 		maturityDate ) 		--Define for using
                            , newDate 			AS ( select '$newDate' 				newDate ) 			--Define for using
                            , firstPaymentDate 	AS ( select '$firstInterestPaymentDate' 	firstPaymentDate )	--Define for using
                            , allDate AS ( --Merge date param as one row
                            SELECT * FROM newDate
                            CROSS JOIN firstPaymentDate
                            CROSS JOIN maturityDate
                            )
                            , InterestSchedule AS ( 
                            WITH RECURSIVE InterestSchedule(n, o, p) as
                            ( SELECT firstPaymentDate n, 0.5 o, firstPaymentDate p from firstPaymentDate
                                UNION ALL
                                SELECT TO_CHAR(n::DATE + CAST((borrowingInterestPaymentTypeCode)||' '||'MONTH' AS INTERVAL), 'yyyymmdd'), o + 0.5, TO_CHAR('$firstInterestPaymentDate'::DATE + CAST((borrowingInterestPaymentTypeCode * o)||' '||'MONTH' AS INTERVAL), 'yyyymmdd') 
                                FROM InterestSchedule n CROSS JOIN BorrowingInterestPaymentTypeCode
                                WHERE n.p < (select maturityDate FROM maturityDate)
                            )select * from InterestSchedule
                            )
                            --			select * FROM InterestSchedule
                            --			
                            , InterestSchedule1 as (select
                            *
                            -- if first payment is EOM then next paymentdate is EOM
                            , case when firstPaymentDate != TO_CHAR((date_trunc('month', firstPaymentDate::date) + interval '1 month' - interval '1 day')::date, 'yyyymmdd') then p else TO_CHAR((date_trunc('month', p::date) + interval '1 month' - interval '1 day')::date, 'yyyymmdd') end p1
                            from
                            InterestSchedule cross join firstPaymentDate
                            )
                            , b as ( --Merge New Date, Schedule Date and Maturity Date
                            SELECT newDate dates from newDate
                            UNION
                            SELECT p1 FROM InterestSchedule1
                            UNION
                            SELECT maturityDate from maturityDate
                            )
                            , c AS ( --Move to next biz date
                            SELECT
                                    b.dates nextBizDay
                                    , *
                            FROM
                                B CROSS JOIN allDate
                            WHERE dates <= maturityDate
                            ORDER BY
                                dates
                            )
                            , d as ( --Filter out the date after maturity date
                            SELECT
                                    CASE WHEN nextBizDay = newdate THEN newdate ELSE nextBizDay END fromDate
                                    , LEAD (nextBizDay,1) OVER (ORDER BY nextBizDay) AS toDate
                                    , *
                                    , ROW_NUMBER() over() monthCount
                            FROM
                                c
                            WHERE dates <= maturityDate
                            ORDER BY
                                dates
                            )
                            , e as ( -- If days in last records of schedule is less than 15 days it will remove one month
                            SELECT
                                *
                                , todate::DATE - fromDate::DATE  dayCount
                                , case 
                                    when todate = maturitydate and (todate::DATE - fromDate::DATE) < 15 THEN monthcount - 1 
                                else monthcount end monthcount1
                            FROM
                                d
                            where toDate IS NOT NULL
                            )
                            ,f as ( --Merge the month that is less than 15 days
                            SELECT
                                MIN(fromdate) fromdate,
                                MAX(todate) todate,
                                MAX(nextbizday) nextbizday,
                                MAX(dates) dates,
                                MAX(newdate) newdate,
                                MAX(firstpaymentdate) firstpaymentdate,
                                MAX(maturitydate) maturitydate,
                                MIN(monthcount) monthcount,
                                SUM(daycount) daycount
                            FROM
                                e 
                            group by monthcount1
                            order by monthcount
                            )
                            , g as ( --Calculate monthN for Pricipal Pay
                            SELECT														
                                *
                                , CEIL(monthcount*borrowinginterestpaymenttypecode/borrowingprinciplepaymenttypecode::NUMERIC)  AS monthN
                                , ROW_NUMBER () OVER (PARTITION BY CEIL(monthcount*borrowinginterestpaymenttypecode/borrowingprinciplepaymenttypecode::NUMERIC)) monthNCount
                                , max(monthCount) OVER () as maxMonthCount
                            FROM f CROSS JOIN BorrowingInterestPaymentTypeCode CROSS JOIN BorrowingPrinciplePaymentTypeCode
                            )
                            , H AS (
                            SELECT
                                *
                                , CASE 
                                    WHEN 
                                            (borrowinginterestpaymenttypecode = borrowingprinciplepaymenttypecode) 
                                        OR
                                            (monthNCount * borrowinginterestpaymenttypecode =  borrowingprinciplepaymenttypecode) --Interval
                                        OR
                                            (maxMonthCount = monthcount) --Last Row
                                            THEN TRUE 
                                    ELSE 
                                        FALSE 
                                    END  isPrinciplePayment
                            FROM
                                G
                            )
                            , Months AS (
                            SELECT * 
                                , SUM(CASE WHEN isprinciplepayment THEN 1 ELSE 0 END) OVER() AS principlePaymentCount
                            FROM H
                            )
                            --			select * FROM Months
                            --			
                            , Days AS (
                            SELECT 
                                TO_CHAR(days, 'yyyymmdd') days,
                                1 as dayCount,
                                $taxRate taxRate,
                                $newTaxRate newTaxRate,
                                '$newTaxRateFromDate' newTaxRateFromDate,
                                TO_CHAR(NULLIF('$newTaxRateToDate','')::DATE - interval '1 day', 'yyyymmdd') newTaxRateToDate,
                                $loanAmount loanBalance,
                                '$currencyCode' currencyCode,
                                $applyInterestRate applyInterestRate,
                                CASE '$borrowingInterestCalculationTypeCode' 
                                    WHEN '01' THEN 360 
                                    WHEN '02' THEN 365 
                                    WHEN '03' THEN (LEFT(TO_CHAR(days, 'yyyymmdd'), 4) || '1231')::DATE - (LEFT(TO_CHAR(days, 'yyyymmdd'), 4) || '0101')::DATE   + 1
                                    ELSE 0 
                                END dayCountPerYear
                            FROM generate_series(
                                '$newDate' :: DATE,
                                '$maturityDate' :: DATE,
                                '1 day'
                            ) AS days CROSS JOIN allDate
                            )
                            , MERG_DAY_MONTH AS (
                            SELECT 
                                A.*
                                , B.toDate paymentDate
                                , B.isPrinciplePayment
                                , B.maturitydate
                                , B.fromDate
                                , B.toDate
                                , B.monthcount
                                , COALESCE(CASE WHEN B.isprinciplepayment THEN 
                                        CASE 
                                            WHEN A.currencyCode = 'USD' THEN TRUNC(A.loanbalance::NUMERIC  / B.principlePaymentCount, 2) 
                                            WHEN A.currencyCode = 'KHR' THEN TRUNC(A.loanbalance::NUMERIC  / B.principlePaymentCount) 
                                            ELSE 0 
                                        END 
                                    ELSE 0 END, 0) principlePaymentAmount
                            FROM
                                Days A 
                                    LEFT JOIN Months B ON A.days = B.todate
                            )
                            --			SELECT * FROM MERG_DAY_MONTH
                            --			
                            , CalculateLoanBalance AS ( 
                            SELECT
                                *
                                , SUM(principlepaymentamount) over (order by days asc rows between unbounded preceding and current row) accumulateSubstractAmount
                                , loanbalance - SUM(principlepaymentamount) over (order by days asc rows between unbounded preceding and current row) loanbalance1
                            FROM
                                MERG_DAY_MONTH
                            )
                            , CalculateLoanBalance1 AS (
                            SELECT
                                *
                                , CASE WHEN days = maturitydate THEN principlepaymentamount + loanbalance1 ELSE principlepaymentamount END principlepaymentamount1
                                , CASE WHEN days = maturitydate THEN loanbalance - (accumulatesubstractamount + loanbalance1) ELSE loanbalance1 END loanbalance2
                                , CASE WHEN days between newtaxratefromdate and newtaxratetodate THEN newtaxrate ELSE taxrate END newTaxRate1
                            FROM
                            CalculateLoanBalance
                            )
                            , CalculateLoanBalance2 AS (
                            SELECT
                                *
                                , ( loanbalance2  * applyinterestrate / 100) / dayCountPerYear dailyInterestAmount --Calculate Interest before calculate loanBalance
                                , (( loanbalance2  * applyinterestrate / 100) / dayCountPerYear) * newTaxRate1 / 100 taxAmount --Calculate tax before calculate loanBalance
                                , CASE WHEN days = maturitydate THEN paymentdate ELSE (SELECT toDate FROM Months AA where A.days <= AA.toDate ORDER BY toDate Limit 1) END paymentdate2
                                , CASE WHEN days between newtaxratefromdate	AND newtaxratetodate THEN 'New withholding tax ' || TO_CHAR(newTaxRate1, 'FM990.90') || '% start from ' || TO_CHAR(newtaxratefromdate::DATE, 'dd/mm/yyyy') || ' to ' || TO_CHAR(newtaxratetodate::DATE + interval '1 day','dd/mm/yyyy')	ELSE '' END			remark
                                , CASE WHEN days between newtaxratefromdate	AND newtaxratetodate THEN newTaxRate ELSE taxrate END		taxrate1
                            FROM
                                CalculateLoanBalance1 A
                            )
                            , CalculateLoanBalance22 as (
                            select --not include first day
                                *
                                , daycount daycount2
                                , dailyinterestamount dailyinterestamount2
                                , taxamount taxamount2
                                , LEAD(paymentdate2) OVER (ORDER BY days)  paymentdate3
                                , LEAD(paymentdate) OVER (ORDER BY days)  paymentdate4
                                , LEAD(days) OVER (ORDER BY days)  days1
                                , LEAD(isprinciplepayment) OVER (ORDER BY days)  isprinciplepayment1
                                , LEAD(fromdate) OVER (ORDER BY days) fromdate2
                                , LEAD(todate) OVER (ORDER BY days) todate2
                                , LEAD(monthcount) OVER (ORDER BY days) monthcount2
                                , LEAD(loanbalance2) OVER (ORDER BY days) loanbalance3
                                , LEAD(principlepaymentamount1) OVER (ORDER BY days) principlepaymentamount2
                            from
                                CalculateLoanBalance2
                            ORDER BY days
                            )
                            , CalculateLoanBalance3 AS (
                            SELECT
                                paymentdate3
                                , MAX(monthcount2)	monthcount
                                , SUM(daycount2) daycount
                                , MAX(taxrate1)	taxrate1
                                , MAX(applyinterestrate)	applyinterestrate
                                , MAX(fromdate2)				fromDate
                                , MAX(toDate2)				toDate
                                , SUM(CASE WHEN paymentdate4 = days1 AND isprinciplepayment1 THEN principlepaymentamount2 ELSE 0 END)				principlepaymentamount1
                                , MIN(loanbalance3)			loanbalance
                                , SUM(dailyinterestamount2)	dailyinterestamount
                                , SUM(taxamount2)			taxamount
                                , MAX(newtaxratefromdate)	newtaxratefromdate
                                , MAX(newtaxratetodate)		newtaxratetodate
                                , MAX(loanbalance)			loanAmount
                                , MAX(currencycode)			currencycode
                                , MAX(remark)				remark
                                , MAX(dayCountPerYear)		dayCountPerYear
                            FROM
                                CalculateLoanBalance22
                            GROUP BY paymentdate3
                            ORDER BY paymentdate3
                            )
                            , CalculateLoanBalance4 AS (
                            SELECT
                                *
                                , CASE WHEN currencycode = 'USD' THEN TRUNC(ROUND(dailyinterestamount, 10), 2) ELSE ROUND(dailyinterestamount) END dailyinterestamount1
                                , CASE WHEN currencycode = 'USD' THEN TRUNC(ROUND(taxamount,10), 2) ELSE ROUND(taxamount) END taxamount1
                            FROM
                                CalculateLoanBalance3
                            WHERE paymentdate3 IS NOT null
                            )
                            SELECT
                            '$borrowingNo'									AS borrowingNo
                            , paymentdate3 									AS paymentApplyDate
                            , monthcount 									AS paymentNumberOfTime
                            , applyinterestrate 							AS applyInterestRate
                            , taxrate1 										AS taxRate
                            , loanbalance 									AS loanBalance
                            , fromdate 										AS scheduleStartDate 
                            , todate 										AS scheduleEndDate
                            , daycount 										AS calcDays
                            , principlepaymentamount1 						AS repayPrincipal
                            , dailyinterestamount1 							AS repayInterest
                            , taxamount1 									AS taxAmount
                            , '01' 											AS ledgerStatusCode
                            , remark 										AS remark
                            FROM
                            CalculateLoanBalance4
        ");

        dd($results);
        
    }
}