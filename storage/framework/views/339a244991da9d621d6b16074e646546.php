<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>

    <style>
        @page {
            margin: 0;
            /* margin-top: 300px;
            padding: 10px 40px */
        }

        .page-break {
            page-break-after: always;
        }

        body {
            margin: 0px;
            font-family: 'Poppins', sans-serif;
            margin: 0 !important;
            padding: 0 !important;
            width: 100%;

        }

        .header-title {
            font-size: 14px;
        }

        .header-desc {
            font-size: 12px;
            display: block;
            margin-bottom: 4px;
            font-weight: normal;
        }

        .header-heading {
            color: #ff5b00;
            font-size: 18px;
            font-weight: normal;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }


        .header-table th,
        .header-table td {
            /* border: 1px solid black; */
            text-align: left
        }

        .header-table th {
            font-weight: normal;
            font-size: 10px;
            color: #ff5b00
        }

        .header-table td {
            font-weight: normal;
            font-size: 10px;
        }

        /**
            * Define the width, height, margins and position of the watermark.
            **/
        #watermark {
            position: fixed;
            bottom: 0px;
            left: 0px;
            /** The width and height may change
                    according to the dimensions of your letterhead
                **/
            /** Your watermark should be behind every content**/
            z-index: -1000;
        }

        .break-before {
            page-break-before: always;
        }
    </style>
</head>

<body>
    <div id="watermark">
        <img src="<?php echo e(public_path() . '/assets/attraction_template.jpg'); ?>" height="100%" width="100%" />
    </div>

    <div>
        <div style="margin-top: 430px;
        padding: 0px 23px">
            <table class="header-table">
                <tbody>
                    <tr>
                        <th style="width:30%;font-size:14px;font-weight:bold;padding-bottom:12px!important">Booking Detail</th>
                        <th></th>
                        <th></th>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Booking ID:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important"><?php echo e($data->crm_id); ?></td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Reservation ID:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important"><?php echo e(preg_match('/_(\d+)$/', $data->crm_id, $matches) ? $matches[1] : ''); ?>

</td>
                    </tr>
                    
                    <tr>
                        <th style="width:30%;font-size:14px;font-weight:bold;padding-bottom:12px!important">Customer Detail</th>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Customer Name:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important"><?php echo e($data->booking->customer->name); ?></td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Passport No:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important"><?php echo e($data->customer_passports ? $data->customer_passports : '-'); ?></td>
                    </tr>
                    
                    <tr>
                        <th style="width:30%;font-size:14px;font-weight:bold;padding-bottom:12px!important">Ticket Detail</th>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Attraction Name:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important"><?php echo e($data->product->name); ?></td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Ticket Name:</td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important"><?php echo e($data->variation->name ?? '-'); ?>

                        </td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Quantity:
                        </td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important"><?php echo e(count($customers)); ?>

                        </td>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Service Date:
                        </td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important"><?php echo e(Carbon\Carbon::parse($data->service_date)->format('d F Y')); ?>

                        </td>
                    </tr>
                    
                    <tr>
                        <th style="width:30%;font-size:14px;font-weight:bold;padding-bottom:12px!important">Agent Detail</th>
                    </tr>
                    <tr>
                        <td style="width:30%;font-size:13px;padding-bottom:12px!important">Agent Name:
                        </td>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important">
                        <?php if(Str::startsWith($data->crm_id, 'CK')): ?>
                            ChawKalayar
                        <?php elseif(Str::startsWith($data->crm_id, 'SH')): ?>
                            Sunshine
                        <?php elseif(Str::startsWith($data->crm_id, 'HN')): ?>
                            Hinn
                        <?php elseif(Str::startsWith($data->crm_id, 'CS')): ?>
                            Chit Su
                        <?php elseif(Str::startsWith($data->crm_id, 'KN')): ?>
                            Ko Nay Myo
                        <?php elseif(Str::startsWith($data->crm_id, 'EM')): ?>
                            Ei Myat
                        <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="width:30%;font-size:13px;font-weight:bold;padding-bottom:12px!important">Payment Status:</th>
                        <td style="width:70%;font-size:13px;font-weight:bold;padding-bottom:12px!important;
    <?php if($data->payment_status === 'fully_paid'): ?> color: green; <?php endif; ?>
    <?php if($data->payment_status === 'partially_paid'): ?> color:#ff5733; <?php endif; ?>
    <?php if($data->payment_status === 'not_paid'): ?> color:red; <?php endif; ?>">
    <?php echo e(ucwords(str_replace('_', ' ', $data->payment_status))); ?>

</td>

                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
<?php /**PATH /var/www/projects/thanywheresale-api/resources/views/pdf/reservation_receipt.blade.php ENDPATH**/ ?>