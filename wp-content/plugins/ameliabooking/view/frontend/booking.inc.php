<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

?>

<script>
  var hasBookingShortcode = (typeof hasBookingShortcode === 'undefined') ? false : true
  var bookingEntitiesIds = (typeof bookingEntitiesIds === 'undefined') ? [] : bookingEntitiesIds
  bookingEntitiesIds.push(
    {
      'trigger': '<?php echo $atts['trigger']; ?>',
      'counter': '<?php echo $atts['counter']; ?>',
      'category': '<?php echo $atts['category']; ?>',
      'service': '<?php echo $atts['service']; ?>',
      'employee': '<?php echo $atts['employee']; ?>',
      'location': '<?php echo $atts['location']; ?>'
    }
  )

  var lazyBookingEntitiesIds = (typeof lazyBookingEntitiesIds === 'undefined') ? [] : lazyBookingEntitiesIds

  if (bookingEntitiesIds[bookingEntitiesIds.length - 1].trigger !== '') {
    lazyBookingEntitiesIds.push(bookingEntitiesIds.pop())
  }
</script>

<div id="amelia-app-booking<?php echo $atts['counter']; ?>" class="amelia-booking amelia-frontend amelia-app-booking<?php echo $atts['trigger'] !== '' ? ' amelia-skip-load amelia-skip-load-' . $atts['counter'] : ''; ?>">
  <booking id="amelia-step-booking<?php echo $atts['counter']; ?>"></booking>
</div>
