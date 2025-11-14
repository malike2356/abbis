-- Update cms_menu_items object_type ENUM to include 'rig-request'
-- Run this if you get "Data truncated for column 'object_type'" error

ALTER TABLE cms_menu_items 
MODIFY COLUMN object_type 
ENUM('page','post','category','custom','product','shop','blog','quote','rig-request','home') 
DEFAULT 'custom';

