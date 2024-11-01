<?php

/// Period values for the /tags/{TAG}/top-askers and /tags/{TAG}/top-answerers routes.
if( !class_exists( 'Period' ) ):
class Period
{
    /// Returns the top users for all time.
    const AllTime = 'all-time';
    /// Returns the top users for the current month.
    const Month = 'month';
}

endif;