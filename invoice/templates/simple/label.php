<html>
<head>
    <title><?php esc_html_e( 'Label', 'hippoo-invoice' ); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body {
            font-family: <?php echo esc_attr( $settings['font_name'] ); ?>;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        th, td {
            padding: 15px;
        }
        .rtl{
            direction: rtl;
        }

        .text-left {
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .no-border {
            border: 0;
        }

        /* shipping table */

        table.shipping tbody tr td {
            border-bottom: 1px solid #E0E0E0;
            padding: 15px 5px;
        }

        .invoice-id {
            font-weight: bold;
            font-size: 13px;
        }

        img.courier_logo {
            max-height: 100px;
        }

        /* rtl */

        .rtl .text-left {
            text-align: right;
        }

        .rtl .text-right {
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="wrapper <?php echo esc_attr( $direction ); ?>">
        <table class="shipping">
            <tbody>
                <tr>
                    <td class="shop-info">
                        <h4 class="text-left"><?php esc_html_e( 'From:', 'hippoo-invoice' ); ?></h4><br>
                        <div class="address"><?php echo wp_kses_post( $shop_address ); ?></div>
                    </td>
                    <td class="invoice-logo text-right">
                        <?php if ( isset( $settings['shipping_show_logo'] ) && ! empty( $shop_logo ) ) : ?>
                            <img src="data:image/jpeg;base64,<?php echo esc_attr( $shop_logo ); ?>" width="48" alt="<?php esc_attr_e( 'Shop Logo', 'hippoo-invoice' ); ?>">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <h4 class="text-left"><?php esc_html_e( 'Ship to:', 'hippoo-invoice' ); ?></h4><br>
                        <div class="address">
                            <?php echo esc_html( $order->get_formatted_shipping_full_name() ); ?><br>
                            <?php echo esc_html( $one_line_address ); ?>
                        </div>
                    </td>
                </tr>
                <?php if ( isset( $settings['shipping_calculate_weight'] ) && ! empty( $settings['shipping_calculate_weight'] ) ) : ?>
                <tr>
                    <td colspan="2" class="additional">
                        <h4><?php esc_html_e( 'Weight:', 'hippoo-invoice' ); ?> <?php echo esc_html( $weight ); ?> <?php echo esc_html( get_option( 'woocommerce_weight_unit' ) ); ?></h4>
                    </td>
                </tr>
                <?php endif; ?>
                <tr class="no-border">
                    <td colspan="2" class="text-center">
                        <h4><?php esc_html_e( 'Invoice', 'hippoo-invoice' ); ?> <?php echo esc_html( $order->get_id() ); ?></h4><br>
                        <div class="invoice-barcode">
                            <img src="data:image/jpeg;base64,<?php echo esc_attr( $invoice_barcode ); ?>" alt="<?php esc_attr_e( 'Invoice Barcode', 'hippoo-invoice' ); ?>">
                            <br><br><br>
                            <?php if ( $settings['shipping_courier_logo'] ) : ?>
                                <img class="courier_logo" src="data:image/jpeg;base64,<?php echo esc_attr( $shipping_courier_logo ); ?>" alt="<?php esc_attr_e( 'Courier Logo', 'hippoo-invoice' ); ?>">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
