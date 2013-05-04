<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2013 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class Gallery_Controller_Albums extends Controller_Items {
  public static function get_siblings($item, $limit=null, $offset=null) {
    // @todo consider creating Model_Item::siblings() if we use this more broadly.
    return $item->parent->children->viewable()->limit($limit)->offset($offset)->find_all();
  }

  /**
   * Add a new album.  This generates the form, validates it, adds the item, and returns a response.
   * This can be used as an ajax dialog (preferable) or a normal view.
   */
  public function action_add() {
    $parent_id = $this->request->arg(0, "digit");
    $parent = ORM::factory("Item", $parent_id);
    if (!$parent->loaded() || !$parent->is_album()) {
      // Parent doesn't exist or isn't an album - fire a 400 Bad Request.
      throw HTTP_Exception::factory(400);
    }
    Access::required("view", $parent);
    Access::required("add", $parent);

    // Build the item model.
    $item = ORM::factory("Item");
    $item->type = "album";
    $item->parent_id = $parent_id;

    // Build the form.
    $form = Formo::form()
      ->add("item", "group")
      ->add("buttons", "group");
    $form->item
      ->add("title", "input")
      ->add("description", "textarea")
      ->add("name", "input")
      ->add("slug", "input");
    $form->buttons
      ->add("submit", "input|submit", t("Create"));

    $form
      ->attr("id", "g-add-album-form")
      ->add_script_url("modules/gallery/assets/albums_form_add.js");
    $form->item
      ->set("label", t("Add an album to %album_title", array("album_title" => $parent->title)));
    $form->item->title
      ->set("label", t("Title"))
      ->set("error_messages", array(
          "not_empty" => t("You must provide a title"),
          "max_length" => t("Your title is too long")
        ));
    $form->item->description
      ->set("label", t("Description"));
    $form->item->name
      ->set("label", t("Directory name"))
      ->set("error_messages", array(
          "no_slashes" => t("The directory name can't contain a \"/\""),
          "no_backslashes" => t("The directory name can't contain a \"\\\""),
          "no_trailing_period" => t("The directory name can't end in \".\""),
          "not_empty" => t("You must provide a directory name"),
          "max_length" => t("Your directory name is too long"),
          "conflict" => t("There is already a movie, photo or album with this name")
        ));
    $form->item->slug
      ->set("label", t("Internet Address"))
      ->set("error_messages", array(
          "conflict" => t("There is already a movie, photo or album with this internet address"),
          "reserved" => t("This address is reserved and can't be used."),
          "not_url_safe" => t("The internet address should contain only letters, numbers, hyphens and underscores"),
          "not_empty" => t("You must provide an internet address"),
          "max_length" => t("Your internet address is too long")
        ));
    $form->buttons
      ->set("label", "");

    // Link the ORM model and call the form event
    $form->item->orm("link", array("model" => $item));
    Module::event("album_add_form", $parent, $form);

    // Load and validate the form.
    if ($form->sent()) {
      if ($form->load()->validate()) {
        // Passed - save item, run event, add to log, send message, then redirect to new item.
        $item->save();
        Module::event("album_add_form_completed", $item, $form);
        GalleryLog::success("content", t("Created an album"),
                            HTML::anchor($item->url(), t("view")));
        Message::success(t("Created album %album_title",
                           array("album_title" => HTML::purify($item->title))));

        if ($this->request->is_ajax()) {
          $this->response->json(array("result" => "success", "location" => $item->url()));
          return;
        } else {
          $this->redirect($item->abs_url());
        }
      } else {
        // Failed - if ajax, return an error.
        if ($this->request->is_ajax()) {
          $this->response->json(array("result" => "error", "html" => (string)$form));
          return;
        }
      }
    }

    // Nothing sent yet (ajax or non-ajax) or item validation failed (non-ajax).
    if ($this->request->is_ajax()) {
      // Send the basic form.
      $this->response->body($form);
    } else {
      // Wrap the basic form in a theme.
      $view_theme = new View_Theme("required/page.html", "other", "item_add");
      $view_theme->page_title = $form->item->get("label");
      $view_theme->content = $form;
      $this->response->body($view_theme);
    }
  }
}
