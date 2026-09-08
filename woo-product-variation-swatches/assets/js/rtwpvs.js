(() => {//#region src/js/frontend.js
(function($) {
	"use strict";
	window.rtWpvsVariationsFromInitialize = function() {
		$(".variations_form").each(function() {
			$(this).wc_variation_form();
		});
	};
	$.fn.rtWpvsVariationSwatchesForm = function() {
		this._variation_form = $(this);
		this.product_variations = this._variation_form.data("product_variations");
		this._attributeTerms = this._variation_form.find(".variations select");
		this._is_ajax = !!this.product_variations;
		this._is_archive = this._variation_form.hasClass("rtwpvs-product-loop-variation") || this._variation_form.hasClass("rtwpvs-archive-variation-wrapper");
		this._out_of_stock = {};
		this._is_mobile = $("body").hasClass("rtwpvs-is-mobile");
		this.start = function() {
			const that = this;
			let attributeSelected = false;
			this._variation_form.find("select.rtwpvs-wc-select").each(function() {
				if ($(this).val()) attributeSelected = true;
			});
			if (attributeSelected) this._variation_form.find(".reset_variations").addClass("show");
			this._variation_form.find(".rtwpvs-terms-wrapper").each(function() {
				let attribute = $(this), wc_select = attribute.parent().find("select.rtwpvs-wc-select");
				attribute.on("touchstart click", ".rtwpvs-term:not(.rtwpvs-radio-term)", function(e) {
					e.preventDefault();
					e.stopPropagation();
					var now = Date.now();
					if (attribute.data("_rtwpvs_last_click") && now - attribute.data("_rtwpvs_last_click") < 200) return;
					attribute.data("_rtwpvs_last_click", now);
					let self = $(this), is_selected = self.hasClass("selected"), term = self.data("term");
					if (is_selected && rtwpvs_params.reselect_clear) term = "";
					wc_select.val(term).trigger("change").trigger("click").trigger("focusin");
					if (that._is_mobile) wc_select.trigger("touchstart");
					self.trigger("focus");
					if (is_selected) self.trigger("rtwpvs-unselected-term", [
						term,
						wc_select,
						this._variation_form
					]);
					else self.trigger("rtwpvs-selected-term", [
						term,
						wc_select,
						this._variation_form
					]);
				});
				attribute.on("change", "input.rtwpvs-radio-button-term:radio", function(e) {
					e.preventDefault();
					e.stopPropagation();
					let radioTerm = $(this), term = radioTerm.val(), termWrapper = radioTerm.parent(".rtwpvs-term.rtwpvs-radio-term"), is_selected = termWrapper.hasClass("selected");
					if (is_selected && rtwpvs_params.reselect_clear) term = "";
					wc_select.val(term).trigger("change").trigger("click").trigger("focusin");
					if (that._is_mobile) wc_select.trigger("touchstart");
					if (rtwpvs_params.reselect_clear) {
						if (is_selected) _.delay(function() {
							radioTerm.prop("checked", false);
							termWrapper.trigger("rtwpvs-unselected-term", [
								term,
								wc_select,
								this._variation_form
							]);
						}, 1);
						else termWrapper.trigger("rtwpvs-selected-term", [
							term,
							wc_select,
							this._variation_form
						]);
					} else if (!rtwpvs_params.reselect_clear) radioTerm.parent(".rtwpvs-term.rtwpvs-radio-term").removeClass("selected disabled").addClass("selected").trigger("rtwpvs-selected-term", [
						term,
						wc_select,
						this._variation_form
					]);
				});
				if (rtwpvs_params.reselect_clear) attribute.on("touchstart click", "input.rtwpvs-radio-button-term:radio", function(e) {
					e.preventDefault();
					e.stopPropagation();
					$(this).trigger("change");
				});
			});
			this._variation_form.on("click.wc-variation-form", ".reset_variations", { variationForm: this._variation_form }, function(e) {
				e.preventDefault();
				$(this).removeClass("show");
			});
			this._variation_form.on("change.wc-variation-form", ".variations select", { variationForm: this._variation_form }, function(e) {
				that._variation_form.find(".reset_variations").addClass("show");
			});
			setTimeout(function() {
				that._variation_form.trigger("reload_product_variations");
				that._variation_form.trigger("rtwpvs_loaded", [that]);
			}, 1);
			this.initVariationURL();
		};
		this.update_trigger = function() {
			this._variation_form.on("rtwpvs_loaded", { that: this }, this.loaded_triggered);
			this._variation_form.on("woocommerce_update_variation_values", this.update_variation_triggered);
			this._variation_form.on("reset_data", { that: this }, this.reset_triggered);
			this._variation_form.on("woocommerce_variation_has_changed", { that: this }, this.variation_has_changed_triggered);
			this._variation_form.on("check_variations.rtwpvs-wc-variation-form", { that: this }, this.term_change);
		};
		this.update_variation_triggered = function(e) {
			$(this).find(".rtwpvs-terms-wrapper").each(function() {
				let attribute = $(this), wc_select = attribute.parent().find("select.rtwpvs-wc-select"), selected = wc_select.find("option:selected").val() || "", current = wc_select.find("option:selected"), itemIndex = wc_select.find("option").eq(1), wc_terms = [];
				wc_select.find("option").each(function() {
					if ($(this).val() !== "") {
						wc_terms.push($(this).val());
						selected = current ? current.val() : itemIndex.val();
					}
				});
				var termsSet = new Set(wc_terms);
				setTimeout(function() {
					attribute.find(".rtwpvs-term").each(function() {
						let item = $(this), term = item.attr("data-term"), isAvailable = termsSet.has(term), isSelected = term === selected;
						item.removeClass("selected disabled");
						if (isSelected) item.addClass("selected");
						else if (!isAvailable) item.addClass("disabled");
						var radio = item.find("input.rtwpvs-radio-button-term:radio");
						if (radio.length) {
							radio.prop("disabled", !isAvailable && !isSelected);
							radio.prop("checked", isSelected);
						}
					});
					attribute.trigger("rtwpvs-terms-updated");
				}, 1);
			});
		};
		this.variation_has_changed_triggered = function(e) {
			if (!e.data.that._is_ajax) $(this).find(".rtwpvs-terms-wrapper").each(function() {
				let attribute = $(this), wc_select = attribute.parent().find("select.rtwpvs-wc-select"), selected = wc_select.find("option:selected").val() || "", current = wc_select.find("option:selected"), itemIndex = wc_select.find("option").eq(1), wc_terms = [];
				wc_select.find("option").each(function() {
					if ($(this).val() !== "") {
						wc_terms.push($(this).val());
						selected = current ? current.val() : itemIndex.val();
					}
				});
				setTimeout(function() {
					attribute.find(".rtwpvs-term").each(function() {
						let item = $(this), term = item.attr("data-term");
						item.removeClass("selected disabled");
						if (term === selected) item.addClass("selected").find("input.rtwpvs-radio-button-term:radio").prop("disabled", false).prop("checked", true);
					});
					attribute.trigger("rtwpvs-terms-updated");
				}, 1);
			});
		};
		this.reset_triggered = function(e) {
			if (e.data.that._is_ajax) $(this).find(".rtwpvs-terms-wrapper").each(function() {
				$(this).find(".rtwpvs-term").removeClass("selected disabled").find("input.rtwpvs-radio-button-term:radio").prop("disabled", false).prop("checked", false);
			});
		};
		this.loaded_triggered = function(e) {
			const that = e.data.that;
			if (that._is_ajax) {
				let attributes = {};
				("object" === typeof that.product_variations ? that.product_variations : JSON.parse(that.product_variations)).map(function(variation) {
					if (variation.attributes) Object.keys(variation.attributes).map(function(attribute) {
						if (!attributes[attribute]) attributes[attribute] = [];
						if (variation.attributes[attribute] && attributes[attribute].indexOf(variation.attributes[attribute]) === -1) attributes[attribute].push(variation.attributes[attribute]);
					});
					else console.log("variation.attributes Type ", typeof variation.attributes);
				});
				$(e.target).find(".rtwpvs-terms-wrapper").each(function() {
					const attribute_name = $(this).data("attribute_name");
					$(this).find(".rtwpvs-term").each(function() {
						let self = $(this), term = self.attr("data-term");
						if (!$.isEmptyObject(attributes) && attributes[attribute_name].indexOf(term) === -1) self.removeClass("selected").addClass("disabled").find("input.rtwpvs-radio-button-term:radio").prop("disabled", true).prop("checked", false);
					});
				});
			}
		};
		this.initVariationURL = function() {
			const that = this;
			let currentUrl = window.location.toString();
			const parent = that.closest(".rtwpvs-product");
			if (this._is_archive) currentUrl = parent.find("a.woocommerce-LoopProduct-link").first().attr("href");
			if (currentUrl) {
				let url = new URL(currentUrl);
				let search = url.searchParams.toString();
				let originalUrl = url.origin + url.pathname;
				this._variation_form.on("check_variations.wc-variation-form", function(event) {
					let attributes = void 0;
					if (rtwpvs_params.has_wc_bundles) {
						url = new URL(currentUrl);
						search = url.searchParams.toString();
						attributes = that.getChosenAttributesBundleSupport();
					} else attributes = that.getChosenAttributes();
					let attributesObject = Object.keys(attributes).reduce(function(attrs, current) {
						if (attributes[current]) attrs[current] = attributes[current];
						return attrs;
					}, {});
					let searchObject = that.urlParamsToObj(search);
					let data = Object.assign({}, searchObject, attributesObject);
					let params = $.param(data);
					if (!that._is_archive && rtwpvs_params.enable_variation_url) window.history.pushState({}, "", that.setUrlParams(originalUrl, params));
					if (that._is_archive && rtwpvs_params.enable_archive_variation_url) parent.find("a:not(.rtwpvs_add_to_cart,.rtsb-quick-checkout-btn)").attr("href", that.setUrlParams(originalUrl, params));
				});
			}
		};
		this.getChosenAttributesBundleSupport = function() {
			let data = {};
			this._attributeTerms.each(function() {
				let attribute_name = $(this).attr("name");
				data[attribute_name] = $(this).val() || "";
			});
			return data;
		};
		this.getChosenAttributes = function() {
			let data = {};
			this._attributeTerms.each(function() {
				const attribute_name = $(this).data("attribute_name") || $(this).attr("name");
				data[attribute_name] = $(this).val() || "";
			});
			return data;
		};
		this.urlParamsToObj = function(search) {
			const params = new URLSearchParams(search);
			let obj = {};
			params.forEach(function(value, key) {
				obj[key] = value;
			});
			return obj;
		};
		this.setUrlParams = function(url, query) {
			if (query) {
				query = query.trim().replace(/^(\?|#|&)/, "").replace(/(\?|#|&)$/, "");
				query = query ? "?" + query : query;
				let start = url.split(/[\?\#]/)[0];
				if (query && /\:\/\/[^\/]*$/.test(start)) start = start + "/";
				const match = url.match(/(\#.*)$/);
				url = start + query;
				if (match) url = url + match[0];
			}
			return url;
		};
		this.term_change = function(e) {
			if (!rtwpvs_params.term_beside_label) return;
			$(this).find("select.rtwpvs-wc-select").each(function() {
				const wc_select = $(this), current = wc_select.find("option:selected"), selected = current.val() || "", current_label = wc_select.closest("tr, .rtwpvs-variation-row").find(".label").first();
				if (!current_label.length) return;
				current_label.find("span").remove();
				if (selected.length > 0) {
					const current_text = current.text();
					const label_text = current_label.text().trim();
					current_label.html(label_text + "<span>:</span> <span>" + current_text + "</span>");
				}
			});
		};
		this.start();
		this.update_trigger();
		return this;
	};
	$.fn.wc_set_variation_attr = function(attr, value) {
		if (void 0 === this.attr("data-o_" + attr)) this.attr("data-o_" + attr, !this.attr(attr) ? "" : this.attr(attr));
		if (false === value) this.removeAttr(attr);
		else this.attr(attr, value);
	};
	$.fn.wc_reset_variation_attr = function(attr) {
		if (void 0 !== this.attr("data-o_" + attr)) this.attr(attr, this.attr("data-o_" + attr));
	};
	$.fn.rtWpvsVariationSwatchesArchiveForm = function() {
		this._variation_form = $(this);
		this._is_archive = this._variation_form.hasClass("rtwpvs-product-loop-variation") || this._variation_form.hasClass("rtwpvs-archive-variation-wrapper");
		this.product_variations = this._variation_form.data("product_variations");
		this._attributeTerms = this._variation_form.find(".variations select");
		this._is_ajax = !!this.product_variations;
		this._wrapper = this._variation_form.closest(rtwpvs_params.archive_product_wrapper);
		this._is_mobile = $("body").hasClass("rtwpvs-is-mobile");
		this._image = this._wrapper.find(rtwpvs_params.archive_image_selector);
		this._cart_button = this._wrapper.find(rtwpvs_params.archive_add_to_cart_button_selector);
		this._cart_button_ajax = this._wrapper.find(".rtwpvs_ajax_add_to_cart");
		this._cart_button_html = this._cart_button.clone().html();
		this._price = this._wrapper.find(rtwpvs_params.archive_product_price_selector);
		this._price_html = this._price.clone().html();
		this._reveal_price = this._variation_form.find(".rtwpvs-reveal-price");
		this._reveal_price_html = this._reveal_price.html();
		this._product_id = this._cart_button.data("product_id");
		this.attributeData = {};
		this.selectedData = {};
		if ($.trim(rtwpvs_params.archive_add_to_cart_button_selector)) {
			this._cart_button = this._wrapper.find(rtwpvs_params.archive_add_to_cart_button_selector);
			this._cart_button_ajax = this._wrapper.find(rtwpvs_params.archive_add_to_cart_button_selector);
		}
		this.resetArchiveVariation = function() {
			let isRevealHover = this._variation_form.hasClass("rtwpvs-reveal-hover") || this._variation_form.closest(".rtwpvs-modal").length > 0, $price = this._wrapper.find(rtwpvs_params.archive_product_price_selector), $view_cart_button = this._wrapper.find(".added_to_cart"), $view_cart_button2 = this._wrapper.find(".added_to_cart_button");
			if (!isRevealHover) $price.html(this._price_html);
			if (this._reveal_price && this._reveal_price.length) this._reveal_price.html(this._reveal_price_html);
			this._cart_button.data("variation_id", "");
			this._cart_button.data("variation", "");
			if (!isRevealHover && !rtwpvs_params.archive_swatches_enable_single_attribute) {
				if (rtwpvs_params.archive_add_to_cart_select_options) this._cart_button.html(rtwpvs_params.archive_add_to_cart_select_options);
				else if (wc_add_to_cart_variation_params.i18n_select_options.trim()) this._cart_button.text(wc_add_to_cart_variation_params.i18n_select_options);
				if ("no" === wc_add_to_cart_variation_params.enable_ajax_add_to_cart) this._cart_button.prop("href", this._cart_button.data("product_permalink"));
			}
			this._cart_button.removeClass("added");
			if ($view_cart_button.length > 0) $view_cart_button.remove();
			if ($view_cart_button2.length > 0) $view_cart_button2.remove();
			this._variation_form.removeClass("rtwpvs-variation-selected");
		};
		this.init_trigger = function() {
			const that = this;
			if (this._is_archive) {
				this._variation_form.on("found_variation.rtwpvs-archive-variation", { variationForm: this._variation_form }, function(event, variation) {
					event.stopPropagation();
					that.variationsImageUpdate(variation);
					let template = false, $template_html = "", $view_cart_button = that._wrapper.find(".added_to_cart"), $view_cart_button2 = that._wrapper.find(".added_to_cart_button"), $price = that._wrapper.find(rtwpvs_params.archive_product_price_selector);
					if (!variation.variation_is_visible) template = wp.template("unavailable-variation-template");
					else template = wp.template("rtwpvs-variation-template");
					$template_html = template({
						variation,
						price_html: $(variation.price_html).unwrap().html() || that._price_html
					});
					$template_html = $template_html.replace("/*<![CDATA[*/", "");
					$template_html = $template_html.replace("/*]]>*/", "");
					var isRevealHover = that._variation_form.hasClass("rtwpvs-reveal-hover") || that._variation_form.closest(".rtwpvs-modal").length > 0;
					if (isRevealHover) {
						if (that._reveal_price.length) {
							var revealHtml = variation.price_html || that._reveal_price_html;
							if (variation.availability_html) revealHtml += "<div class=\"woocommerce-variation-availability\">" + variation.availability_html + "</div>";
							that._reveal_price.html(revealHtml);
						}
					} else {
						$price.html($template_html);
						if (that._reveal_price.length) that._reveal_price.html(variation.price_html || that._reveal_price_html);
					}
					that._cart_button.data("variation_id", variation.variation_id);
					that._cart_button.data("variation", that.getChosenAttributes());
					that._variation_form.find(".rtwpvs-hover-add-to-cart").data("variation_id", variation.variation_id).data("variation", that.getChosenAttributes());
					if (!isRevealHover && !rtwpvs_params.archive_swatches_enable_single_attribute) {
						if (rtwpvs_params.archive_add_to_cart_text) that._cart_button.html(rtwpvs_params.archive_add_to_cart_text);
						else if (wc_add_to_cart_variation_params.i18n_add_to_cart.trim()) that._cart_button.text(wc_add_to_cart_variation_params.i18n_add_to_cart);
						if ("no" === wc_add_to_cart_variation_params.enable_ajax_add_to_cart) {
							const params = $.param(Object.assign({}, {
								"add-to-cart": that._product_id,
								variation_id: variation.variation_id
							}));
							that._cart_button.prop("href", that.setUrlParams(that._cart_button.data("add_to_cart_url"), params));
						}
					}
					that._cart_button.removeClass("added");
					if ($view_cart_button.length > 0) $view_cart_button.remove();
					if ($view_cart_button2.length > 0) $view_cart_button2.remove();
					if (variation.variation_id && variation.is_purchasable && variation.is_in_stock) that._variation_form.addClass("rtwpvs-variation-selected");
					else that._variation_form.removeClass("rtwpvs-variation-selected");
				});
				this._variation_form.on("reset_image.rtwpvs-archive-variation", { variationForm: this._variation_form }, function(event) {
					that.variationsImageUpdate(false);
				});
				this._variation_form.on("reset_data.rtwpvs-archive-variation", { variationForm: this._variation_form }, function(event) {
					that.resetArchiveVariation();
				});
				this._variation_form.on("change.wc-variation-form", ".variations select", { variationForm: this._variation_form }, function(e) {
					that._wrapper.find(rtwpvs_params.archive_image_selector).addClass("rtwpvs-image-load").one("webkitAnimationEnd oanimationend msAnimationEnd animationend webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend", function() {
						$(this).removeClass("rtwpvs-image-load");
					});
				});
				this._variation_form.on("check_variations.rtwpvs-wc-variation-form", { that: this }, this.term_change);
			}
			const $ajax_buttons = this._cart_button_ajax.add(this._variation_form.find(".rtwpvs-hover-add-to-cart"));
			$ajax_buttons.off("click.rtwpvs-archive-add-to-cart");
			$ajax_buttons.on("click.rtwpvs-archive-add-to-cart", function(event) {
				const $button = $(this);
				const isRevealButton = $button.hasClass("rtwpvs-hover-add-to-cart");
				if (rtwpvs_params.archive_swatches_enable_single_attribute && !isRevealButton) return true;
				if (!$button.data("variation_id")) return true;
				event.preventDefault();
				event.stopPropagation();
				$button.removeClass("added").addClass("loading");
				const data = { action: "rtwpvs_add_variation_to_cart" };
				$.each($button.data(), function(key, value) {
					data[key] = value;
				});
				$(document.body).trigger("adding_to_cart", [$button, data]);
				$.post(wc_add_to_cart_variation_params.ajax_url.toString(), data, function(response) {
					if (!response) return;
					if (response.error && response.product_url) {
						window.location = response.product_url;
						return;
					}
					if (wc_add_to_cart_params.cart_redirect_after_add === "yes") {
						window.location = wc_add_to_cart_params.cart_url;
						return;
					}
					$(document.body).trigger("added_to_cart", [
						response.fragments,
						response.cart_hash,
						$button
					]);
				});
			});
		};
		this.term_change = function(e) {
			$(this).find(".rtwpvs-variation-terms-wrapper").each(function() {
				let attribute = $(this), wc_select = attribute.find("select.rtwpvs-wc-select"), selected = wc_select.find("option:selected").val() || "", current = wc_select.find("option:selected"), itemIndex = wc_select.find("option").eq(1), wc_terms = [];
				wc_select.find("option").each(function() {
					if ($(this).val() !== "") {
						wc_terms.push($(this).val());
						selected = current ? current.val() : itemIndex.val();
					}
				});
				const current_label = attribute.find(".rtwpvs-label");
				current_label.find("span").remove();
				if (selected.length > 0) {
					const current_text = current.text();
					const selected_value = current_label.text().trim() + " <span>" + current_text + "</span>";
					current_label.html(selected_value);
				}
			});
		};
		this.setUrlParams = function(url, query) {
			if (query) {
				query = query.trim().replace(/^(\?|#|&)/, "").replace(/(\?|#|&)$/, "");
				query = query ? "?" + query : query;
				let start = url.split(/[\?\#]/)[0];
				if (query && /\:\/\/[^\/]*$/.test(start)) start = start + "/";
				const match = url.match(/(\#.*)$/);
				url = start + query;
				if (match) url = url + match[0];
			}
			return url;
		};
		this.urlParamsToObj = function(search) {
			const params = new URLSearchParams(search);
			let obj = {};
			params.forEach(function(value, key) {
				obj[key] = value;
			});
			return obj;
		};
		this.init = function() {
			this.init_trigger();
			const that = this;
			_.delay(function() {
				that.setDefaultImages();
				that._variation_form.trigger("rtwpvs_archive_init", [that, that.product_variations]);
				$(document).trigger("rtwpvs_archive_init_loaded", [that._variation_form, that.product_variations]);
			}, 2);
		};
		this.setDefaultImages = function() {
			let that = this;
			_.delay(function() {
				that._variation_form.off("mouseenter.archive-image-hover");
				if (rtwpvs_params.archive_swatches_display_event === "hover") that._variation_form.on("mouseenter.archive-image-hover", ".rtwpvs-terms-wrapper > .rtwpvs-term:not(.disabled)", function(event) {
					event.stopPropagation();
					if ($(this).hasClass("selected")) return;
					$(this).trigger("click").trigger("focusin");
					if (that._is_mobile) $(this).trigger("touchstart");
				});
			}, 2);
		};
		this.variationsImageUpdate = function(variation) {
			const product_image = this._wrapper.find(rtwpvs_params.archive_image_selector);
			var attrs = [
				"src",
				"height",
				"width",
				"srcset",
				"sizes",
				"title",
				"alt"
			];
			if (variation && variation.image && variation.image.thumb_src && variation.image.thumb_src.length > 1) {
				var img = variation.image;
				var vals = [
					img.thumb_src,
					img.thumb_src_h,
					img.thumb_src_w,
					img.thumb_srcset,
					img.thumb_sizes,
					img.title,
					img.alt
				];
				for (var i = 0; i < attrs.length; i++) product_image.wc_set_variation_attr(attrs[i], vals[i]);
			} else for (var j = 0; j < attrs.length; j++) product_image.wc_reset_variation_attr(attrs[j]);
			$(document).trigger("rtwpvs_archive_variation_image_updated", [this._wrapper, variation]);
		};
		this.isMatch = function(variation_attributes, attributes) {
			let match = true;
			for (let attr_name in variation_attributes) if (variation_attributes.hasOwnProperty(attr_name)) {
				let val1 = variation_attributes[attr_name];
				let val2 = attributes[attr_name];
				if (val1 !== void 0 && val2 !== void 0 && val1.length !== 0 && val2.length !== 0 && val1 !== val2) match = false;
			}
			return match;
		};
		this.findMatchingVariations = function(variations, attributes) {
			let matching = [];
			for (let i = 0; i < variations.length; i++) {
				let variation = variations[i];
				if (this.isMatch(variation.attributes, attributes)) matching.push(variation);
			}
			return matching;
		};
		this.getChosenAttributes = function() {
			let data = {};
			this._attributeTerms.each(function() {
				const attribute_name = $(this).data("attribute_name") || $(this).attr("name");
				data[attribute_name] = $(this).val() || "";
			});
			return data;
		};
		this.init();
		$(document).trigger("rtwpvs_archive", [this._variation_form]);
	};
	window.rtWpvsLoadArchiveVariations = function() {
		if (!rtwpvs_params.enable_ajax_archive_variation) return;
		function handleIntersect(entries, observer) {
			entries.forEach(function(entry) {
				if (entry.isIntersecting) {
					const vWrap = entry.target;
					const $vWrap = $(vWrap);
					if (!vWrap.classList.contains("rtwpvs_av_loading") || !vWrap.classList.contains("rtwpvs_av_loaded")) {
						const product_id = parseInt(vWrap.getAttribute("data-product_id"), 10);
						const product_variations = JSON.parse(vWrap.getAttribute("data-product_variations"));
						if (rtwpvs_params.enable_ajax_archive_variation && product_variations === false && product_id) $.ajax({
							type: "POST",
							url: rtwpvs_params.ajax_url,
							data: {
								action: "rtwpvs_load_product_variation",
								rtwpvs_nonce: rtwpvs_params.nonce,
								product_id
							},
							beforeSend: function() {
								vWrap.classList.add("rtwpvs_av_loading");
							},
							success: function(response) {
								if (response.success) {
									vWrap.classList.add("rtwpvs_av_loaded");
									$(vWrap).data("product_variations", response.data);
									setTimeout(function() {
										$vWrap.on("wc_variation_form", function() {
											$(this).rtWpvsVariationSwatchesForm();
											$(this).rtWpvsVariationSwatchesArchiveForm();
										}).wc_variation_form();
									}, 100);
								}
							},
							error: function error() {
								console.error("Error Loading Data...");
							},
							complete: function complete() {
								$vWrap.removeClass("rtwpvs_av_loading");
							}
						});
					}
					observer.unobserve(vWrap);
				}
			});
		}
		if (!window._rtwpvs_archive_observer) window._rtwpvs_archive_observer = new IntersectionObserver(handleIntersect, {
			root: null,
			rootMargin: "0px",
			threshold: 0
		});
		const variationWrapper = document.querySelectorAll(".rtwpvs-archive-variation-wrapper:not(.rtwpvs_av_loaded):not(.rtwpvs_av_loading)");
		for (let i = 0; i < variationWrapper.length; i++) window._rtwpvs_archive_observer.observe(variationWrapper[i]);
	};
	window.rtWpvsWithoutAjaxVariations = function() {
		if (rtwpvs_params.enable_ajax_archive_variation) return;
		const selector = $(".variations_form").hasClass("rtwpvs-product-loop-variation") ? ".variations_form.rtwpvs-product-loop-variation" : ".variations_form.rtwpvs-archive-variation-wrapper";
		$(document).on("wc_variation_form", selector, function() {
			$(this).rtWpvsVariationSwatchesForm();
			$(this).rtWpvsVariationSwatchesArchiveForm();
		});
		/**
		* yith_infs_added_elem : Support for Yith Infinite Scroll ( Tested )
		* yith-wcan-ajax-filtered : Support for Yith Ajax Filter ( Tested )
		* post-load : Support for Jetpack's Infinite Scroll ( Tested )
		* quick-view-displayed : WooCommerce QuickPopUp Support ( Tested )
		* wood-images-loaded : Support for Woodmart theme
		* astraInfinitePaginationLoaded : Support astra pro ( Tested )
		* berocket_ajax_products_loaded : Support for berocket ajax filters ( Tested )
		* facetwp-loaded : FacetWP Load More
		**/
		$(document).on("yith_infs_added_elem yith-wcan-ajax-filtered post-load quick-view-displayed wood-images-loaded astraInfinitePaginationLoaded berocket_ajax_products_loaded facetwp-loaded", function() {
			rtWpvsVariationsFromInitialize();
		});
		$(".shop-container .products").on("append.infiniteScroll", function(event, response, path) {
			rtWpvsVariationsFromInitialize();
		});
		$("body").on("aln_reloaded", function() {
			_.delay(function() {
				rtWpvsVariationsFromInitialize();
			}, 100);
		});
		$(document.body).on("konte_products_loaded", function() {
			rtWpvsVariationsFromInitialize();
		});
	};
	window.rtWpvsWithAjaxVariations = function() {
		if (!rtwpvs_params.enable_ajax_archive_variation) return;
		/**
		* yith_infs_added_elem : Support for Yith Infinite Scroll ( Tested )
		* yith-wcan-ajax-filtered : Support for Yith Ajax Filter ( Tested )
		* post-load : Support for Jetpack's Infinite Scroll ( Tested )
		* quick-view-displayed : WooCommerce QuickPopUp Support ( Tested )
		* wood-images-loaded : Support for Woodmart theme
		* astraInfinitePaginationLoaded : Support astra pro ( Tested )
		* berocket_ajax_products_loaded : Support for berocket ajax filters ( Tested )
		* facetwp-loaded : FacetWP Load More
		**/
		$(document).on("yith_infs_added_elem yith-wcan-ajax-filtered post-load quick-view-displayed wood-images-loaded astraInfinitePaginationLoaded berocket_ajax_products_loaded facetwp-loaded", function() {
			rtWpvsLoadArchiveVariations();
		});
		$(".shop-container .products").on("append.infiniteScroll", function(event, response, path) {
			rtWpvsLoadArchiveVariations();
		});
		$("body").on("aln_reloaded", function() {
			_.delay(function() {
				rtWpvsLoadArchiveVariations();
			}, 100);
		});
		$(document.body).on("konte_products_loaded", function() {
			rtWpvsLoadArchiveVariations();
		});
	};
	/**
	* Re-initialize archive variation swatches after the WooCommerce
	* "Product Collection" block performs an Interactivity API (client-side)
	* pagination / navigation.
	*
	* The block swaps the product grid in place without firing any of the
	* jQuery events the plugin already listens to, so the freshly injected
	* product cards never get their swatch handlers bound. A MutationObserver
	* watches the Product Collection wrapper and re-runs the proper
	* initializer once new swatch cards are detected.
	*
	* @return {void}
	*/
	window.rtWpvsBlockPaginationSupport = function() {
		if (typeof window.MutationObserver === "undefined") return;
		const containers = document.querySelectorAll(".wp-block-woocommerce-product-collection");
		if (!containers.length) return;
		let debounce = null;
		const reinit = function() {
			clearTimeout(debounce);
			debounce = setTimeout(function() {
				if (rtwpvs_params.enable_ajax_archive_variation) rtWpvsLoadArchiveVariations();
				else rtWpvsVariationsFromInitialize();
				window.rtWpvsUnclipArchiveSwatches();
			}, 150);
		};
		const containsSwatch = function(nodes) {
			for (let i = 0; i < nodes.length; i++) {
				const node = nodes[i];
				if (1 !== node.nodeType) continue;
				if (node.matches && node.matches(".rtwpvs-archive-variation-wrapper") || node.querySelector && node.querySelector(".rtwpvs-archive-variation-wrapper")) return true;
			}
			return false;
		};
		containers.forEach(function(container) {
			new MutationObserver(function(mutations) {
				for (let i = 0; i < mutations.length; i++) if (mutations[i].addedNodes.length && containsSwatch(mutations[i].addedNodes)) {
					reinit();
					break;
				}
			}).observe(container, {
				childList: true,
				subtree: true
			});
		});
	};
	rtWpvsWithoutAjaxVariations();
	rtWpvsWithAjaxVariations();
	window.rtWpvsRelocateRevealPanels = function() {
		$(".rtwpvs-reveal-hover-wrapper").each(function() {
			const $wrapper = $(this);
			const $card = $wrapper.closest(".rtwpvs-product");
			if ($card.length && $wrapper.parent()[0] !== $card[0]) $card.append($wrapper);
		});
	};
	window.rtWpvsUnclipArchiveSwatches = function() {
		$(".rtwpvs-archive-variation-wrapper").each(function() {
			const $wrap = $(this);
			if ($wrap.hasClass("rtwpvs-reveal-hover") || $wrap.closest(".rtwpvs-modal, .rtwpvs-reveal-hover-wrapper").length) return;
			const card = $wrap.closest(".rtwpvs-product")[0];
			if (!card) return;
			let el = this.parentElement;
			while (el && el !== card) {
				const cs = window.getComputedStyle(el);
				if (cs.overflow === "hidden" || cs.overflow === "clip" || cs.overflowY === "hidden" || cs.overflowY === "clip") {
					el.classList.add("rtwpvs-unclip-meta");
					if (el.classList.contains("product-loop-meta")) el.classList.add("no-transform");
				}
				el = el.parentElement;
			}
		});
	};
	window.rtWpvsSyncRevealWrapperPadding = function() {
		$(".rtwpvs-reveal-hover-wrapper").each(function() {
			const parent = this.offsetParent;
			if (!parent) return;
			const cs = window.getComputedStyle(parent);
			this.style.padding = cs.paddingTop + " " + cs.paddingRight + " " + cs.paddingBottom + " " + cs.paddingLeft;
		});
	};
	let rtWpvsRevealResizeTimer = null;
	$(window).on("resize.rtwpvs-reveal", function() {
		clearTimeout(rtWpvsRevealResizeTimer);
		rtWpvsRevealResizeTimer = setTimeout(function() {
			window.rtWpvsUnclipArchiveSwatches();
			window.rtWpvsSyncRevealWrapperPadding();
		}, 150);
	});
	$(document).ready(function() {
		rtWpvsLoadArchiveVariations();
		rtWpvsBlockPaginationSupport();
		window.rtWpvsUnclipArchiveSwatches();
		window.rtWpvsRelocateRevealPanels();
		window.rtWpvsSyncRevealWrapperPadding();
	});
	$(document).on("wc_variation_form", ".variations_form.cart", function() {
		$(this).rtWpvsVariationSwatchesForm();
	});
	$("body").on("click", ".rtwpvs-details-term-more", function() {
		const $that = $(this);
		$that.parents(".rtwpvs-terms-wrapper").removeClass("has-more-variation");
		$that.remove();
	});
	const rtWpvsModal = {
		open: function($modal, trigger) {
			if (!$modal.length) return;
			let $card = trigger ? $(trigger).closest(".rtwpvs-product") : $();
			if (!$card.length) $card = $modal.closest(".rtwpvs-product");
			if ($card.length && $modal.parent()[0] !== $card[0]) $modal.appendTo($card);
			$modal.addClass("is-open").attr("aria-hidden", "false");
			$("body").addClass("rtwpvs-modal-open");
			$modal.data("rtwpvs-trigger", trigger || null);
			const $focus = $modal.find(".rtwpvs-modal__close").first();
			if ($focus.length) $focus.trigger("focus");
		},
		close: function($modal) {
			if (!$modal.length) return;
			const trigger = $modal.data("rtwpvs-trigger");
			$modal.removeClass("is-open").attr("aria-hidden", "true");
			if (!$(".rtwpvs-modal.is-open").length) $("body").removeClass("rtwpvs-modal-open");
			if (trigger) $(trigger).trigger("focus");
		}
	};
	$(document).on("click", "[data-rtwpvs-modal-open]", function(e) {
		const id = $(this).attr("data-rtwpvs-modal-open"), $modal = $(document.getElementById(id));
		if (!$modal.length) return;
		e.preventDefault();
		rtWpvsModal.open($modal, this);
	});
	$(document).on("click", "[data-rtwpvs-modal-close]", function(e) {
		e.preventDefault();
		rtWpvsModal.close($(this).closest(".rtwpvs-modal"));
	});
	$(document).on("keydown", "[data-rtwpvs-modal-close]", function(e) {
		if (e.key === "Enter" || e.key === " " || e.keyCode === 13 || e.keyCode === 32) {
			e.preventDefault();
			rtWpvsModal.close($(this).closest(".rtwpvs-modal"));
		}
	});
	$(document).on("keydown.rtwpvs-modal", function(e) {
		if (e.key === "Escape" || e.keyCode === 27) rtWpvsModal.close($(".rtwpvs-modal.is-open"));
	});
})(jQuery);
//#endregion
})();