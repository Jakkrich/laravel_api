<?php

public function Datatables(Request $request)
    {
        $fields_read = [
            'id',
            'member_code',
            'member_type',
            'idcard',
            // 'birth_day',
            // 'birth_day_date',
            // 'birth_day_month',
            // 'birth_day_year',
            'email',

            // 'cover_image_path',
            // 'status',
            // 'last_login',
            // 'last_login_ip',
            // 'IsReceiveLetter',
            // 'userid_idm',
            // 'isApplyPolicy',
            // 'applyPolicyDate',

            # FIX รอ Hr platform
            'mobile',
            // 'address',
            // 'province_id',
            // 'amphur_id',
            // 'district_id',
            // 'province',
            // 'amphur',
            // 'district',
            // 'zipcode',
        ];
        $data = Member::select($fields_read)->where('member_code', '!=', 'admin');
        // if (!empty($request->member_type)) {
        //     $data = Member::select('id', 'member_code')->where('member_code', '17055');
        // }

        if (!empty($request->member_type) || in_array($request->member_type, ['1', '2', '3'])) {
            $data = $data->where('member_type', $request->member_type);
        }

        if (!empty($request->from_date)) {
            $orgDate = $request->from_date;
            $newDate = \DateTime::createFromFormat('d/m/Y', $orgDate);
            $data = $data->whereDate('created_at', '>=', $newDate->format('Y-m-d'));
        }

        if (!empty($request->to_date)) {
            $orgDate = $request->to_date;
            $newDate = \DateTime::createFromFormat('d/m/Y', $orgDate);
            $data = $data->whereDate('created_at', '<=', $newDate->format('Y-m-d'));
        }

        if ($request->has('isDeadDebtAllowance3M') || $request->has('isDeadDebtAllowance') || $request->has('isForwardAllowance')) {
            $datas = [];
            $user = new User;
            $dd = $data->get();
            foreach ($dd as $d) {
                $u = $user->getProfile($d['member_code'], $d['member_type']);
                $isDeadDebtAllowance3M = $this->calInvoiceReceipt($u['member_code'], $u['member_type'], 'isDeadDebtAllowance3M');
                $isDeadDebtAllowance = $this->calInvoiceReceipt($u['member_code'], $u['member_type'], 'isDeadDebtAllowance');
                $isForwardAllowance = $this->calInvoiceReceipt($u['member_code'], $u['member_type'], 'isForwardAllowance');
                if ($request->has('isDeadDebtAllowance3M')) {
                    if ($request->isDeadDebtAllowance3M == 'on' && $isDeadDebtAllowance3M == 'Y') {
                        array_push($datas, $d['id']);
                    }
                }
                if ($request->has('isDeadDebtAllowance')) {
                    if ($request->isDeadDebtAllowance == 'on' && $isDeadDebtAllowance == 'Y') {
                        array_push($datas, $datas['id']);
                    }
                }
                if ($request->has('isForwardAllowance')) {
                    if ($request->isForwardAllowance == 'on' && $isForwardAllowance == 'Y') {
                        array_push($datas, $d['id']);
                    }
                }
            }
            if ($datas) {
                $data = Member::select($fields_read)->where('id', $datas);
            } else {
                $data = Member::select($fields_read)->where('id', '=', 0);
            }
        }

        $datatables = app('datatables');
        return $datatables->eloquent($data)
            ->addIndexColumn()
            ->addColumn('emp_code', function (Member $member) {
                if ($member->member_type == '1') {
                    return $member->member_code;
                } else
                    return "-";
            })
            ->addColumn('member_code', function (Member $member) {
                $vmember = VMemberAll4::where('EMPLOYEE_ID', '=', $member->member_code)
                    ->where('TYPE_NO', '=', $member->member_type)
                    ->first();
                if ($vmember) {
                    if ($member->member_type == '1') {
                        return $vmember['SERIAL_NO'];
                    }
                }
                return $member->member_code;
            })
            ->addColumn('idcards', function (Member $member) {
                $f = 'SERIAL_NO';
                if ($member->member_type == '1') {
                    $f = 'EMPLOYEE_ID';
                }
                $vmember = VMemberAll4::where($f, '=', $member->member_code)
                    ->where('TYPE_NO', '=', $member->member_type)
                    ->first();
                if ($vmember)
                    return $vmember['E_IDEN_NO'];
                else
                    return "-";
            })
            ->addColumn('fullname', function (Member $member) {
                $f = 'SERIAL_NO';
                if ($member->member_type == '1') {
                    $f = 'EMPLOYEE_ID';
                }
                $vmember = VMemberAll4::where($f, '=', $member->member_code)
                    ->where('TYPE_NO', '=', $member->member_type)
                    ->first();
                if ($vmember)
                    return $vmember['TIT_FULL_TNAME'] .  $vmember['E_NAMEF'] . " " . $vmember['E_NAMEL'];
                else
                    return $member->member_type . "-" . $member->member_code;
            })
            ->addColumn('isDeadDebtAllowance3M', function (Member $member) { // ชำระเกิน 3 เดือน
                if ($member) {
                    return $this->calInvoiceReceipt($member->member_code, $member->member_type, 'isDeadDebtAllowance3M');
                } else {
                    return '-';
                }
            })
            ->addColumn('isDeadDebtAllowance', function (Member $member) {  // ค้างชำระ
                if ($member) {
                    return $this->calInvoiceReceipt($member->member_code, $member->member_type, 'isDeadDebtAllowance');
                } else {
                    return '-';
                }
            })
            ->addColumn('isForwardAllowance', function (Member $member) {   // ชำระเกิน
                if ($member) {
                    return $this->calInvoiceReceipt($member->member_code, $member->member_type, 'isForwardAllowance');
                } else {
                    return '-';
                }
            })
            ->blacklist(['id'])
            ->make(true);
    }
    
?>
