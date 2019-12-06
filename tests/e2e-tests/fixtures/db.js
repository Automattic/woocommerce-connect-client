/**
 * @format
 */

/**
 * External dependencies
 */
import Sequelize, { Model } from 'sequelize';
import sequelize_fixtures from 'sequelize-fixtures';

const db = {
	models: {},
	sequelize: new Sequelize(
		process.env.WP_MYSQL_E2E_DB,
		process.env.WP_MYSQL_E2E_USERNAME,
		process.env.WP_MYSQL_E2E_PASSWORD || null,
		{
			host: ( process.env.WP_MYSQL_E2E_HOST || '127.0.0.1' ),
			dialect: 'mysql',
			define: {
				timestamps: false
			}
		}
	)
};

class Product extends Model {};
class ProductPrice extends Model {};
class Order extends Model {};
class OrderMeta extends Model {};
class OrderLineItem extends Model {};
class OrderLineItemMeta extends Model {};

db.models.Product = Product.init({
	ID: {
		type: Sequelize.BIGINT( 20 ),
		primaryKey: true
	},
	post_author: {
		type: Sequelize.BIGINT( 20 ),
		allowNull: false,
		defaultValue: 1
	},
	post_date: {
		type: Sequelize.DATE,
		allowNull: false,
		defaultValue: Sequelize.NOW
	},
	post_date_gmt : {
		type: Sequelize.DATE,
		allowNull: false,
		defaultValue: Sequelize.NOW
	},
	post_content  : {
		type: Sequelize.TEXT,
		allowNull: false,
		defaultValue: ''
	},
	post_title: {
		type: Sequelize.TEXT( 'tiny' ),
		allowNull: false
	},
	post_excerpt: {
		type: Sequelize.TEXT( 'tiny' ),
		allowNull: false,
		defaultValue: ''
	},
	post_status: {
		type: Sequelize.STRING( 20 ),
		allowNull: false,
		defaultValue: 'publish'
	},
	comment_status: {
		type: Sequelize.STRING( 20 ),
		allowNull: false,
		defaultValue: 'open'
	},
	ping_status: {
		type: Sequelize.STRING( 20 ),
		allowNull: false,
		defaultValue: 'closed'
	},
	post_password: {
		type: Sequelize.STRING,
		allowNull: false,
		defaultValue: ''
	},
	post_name: {
		type: Sequelize.STRING( 200 ),
		allowNull: false,
		defaultValue: ''
	},
	to_ping: {
		type: Sequelize.TEXT( 'tiny' ),
		allowNull: false,
		defaultValue: ''
	},
	pinged: {
		type: Sequelize.TEXT( 'tiny' ),
		allowNull: false,
		defaultValue: ''
	},
	post_modified : {
		type: Sequelize.DATE,
		allowNull: false,
		defaultValue: Sequelize.NOW
	},
	post_modified_gmt: {
		type: Sequelize.DATE,
		allowNull: false,
		defaultValue: Sequelize.NOW
	},
	post_content_filtered: {
		type: Sequelize.TEXT,
		allowNull: false,
		defaultValue: ''
	},
	post_parent: {
		type: Sequelize.BIGINT( 20 ),
		allowNull: false,
		defaultValue: 0
	},
	guid: {
		type: Sequelize.STRING,
		allowNull: false,
		defaultValue: ''
	},
	menu_order: {
		type: Sequelize.INTEGER,
		allowNull: false,
		defaultValue: 0
	},
	post_type: {
		type: Sequelize.STRING(20),
		allowNull: false,
		defaultValue: 'product'
	},
	post_mime_type: {
		type: Sequelize.STRING(100),
		allowNull: false,
		defaultValue: ''
	},
	comment_count: {
		type: Sequelize.BIGINT( 20 ),
		allowNull: false,
		defaultValue: 0
	}
}, {
  	sequelize: db.sequelize,
  	modelName: 'wp_posts'
});

db.models.ProductPrice = ProductPrice.init({
  	meta_id: {
    	type: Sequelize.BIGINT( 20 ),
    	primaryKey: true
  	},
  	post_id: {
    	type: Sequelize.BIGINT( 20 ),
  	},
  	meta_key: {
    	type: Sequelize.STRING,
    	allowNull: false,
		defaultValue: '_price'
  	},
  	meta_value: {
    	type: Sequelize.STRING
  	}
}, {
  	sequelize: db.sequelize,
  	modelName: 'wp_postmeta'
});


db.models.Order = Order.init({
	ID: {
		type: Sequelize.BIGINT( 20 ),
		primaryKey: true,
		autoIncrement: true
	},
	post_author: {
		type: Sequelize.BIGINT( 20 ),
		allowNull: false,
		defaultValue: 1
	},
	post_date: {
		type: Sequelize.DATE,
		allowNull: false,
		defaultValue: Sequelize.NOW
	},
	post_date_gmt : {
		type: Sequelize.DATE,
		allowNull: false,
		defaultValue: Sequelize.NOW
	},
	post_content  : {
		type: Sequelize.TEXT,
		allowNull: false,
		defaultValue: ''
	},
	post_title: {
		type: Sequelize.TEXT( 'tiny' ),
		allowNull: false
	},
	post_excerpt: {
		type: Sequelize.TEXT( 'tiny' ),
		allowNull: false,
		defaultValue: ''
	},
	post_status: {
		type: Sequelize.STRING( 20 ),
		allowNull: false,
		defaultValue: 'wc-processing'
	},
	comment_status: {
		type: Sequelize.STRING( 20 ),
		allowNull: false,
		defaultValue: 'closed'
	},
	ping_status: {
		type: Sequelize.STRING( 20 ),
		allowNull: false,
		defaultValue: 'closed'
	},
	post_password: {
		type: Sequelize.STRING,
		allowNull: false,
		defaultValue: ''
	},
	post_name: {
		type: Sequelize.STRING( 200 ),
		allowNull: false,
		defaultValue: ''
	},
	to_ping: {
		type: Sequelize.TEXT( 'tiny' ),
		allowNull: false,
		defaultValue: ''
	},
	pinged: {
		type: Sequelize.TEXT( 'tiny' ),
		allowNull: false,
		defaultValue: ''
	},
	post_modified : {
		type: Sequelize.DATE,
		allowNull: false,
		defaultValue: Sequelize.NOW
	},
	post_modified_gmt: {
		type: Sequelize.DATE,
		allowNull: false,
		defaultValue: Sequelize.NOW
	},
	post_content_filtered: {
		type: Sequelize.TEXT,
		allowNull: false,
		defaultValue: ''
	},
	post_parent: {
		type: Sequelize.BIGINT( 20 ),
		allowNull: false,
		defaultValue: 0
	},
	guid: {
		type: Sequelize.STRING,
		allowNull: false,
		defaultValue: ''
	},
	menu_order: {
		type: Sequelize.INTEGER,
		allowNull: false,
		defaultValue: 0
	},
	post_type: {
		type: Sequelize.STRING(20),
		allowNull: false,
		defaultValue: 'shop_order'
	},
	post_mime_type: {
		type: Sequelize.STRING(100),
		allowNull: false,
		defaultValue: ''
	},
	comment_count: {
		type: Sequelize.BIGINT( 20 ),
		allowNull: false,
		defaultValue: 0
	}
}, {
  	sequelize: db.sequelize,
  	modelName: 'wp_posts'
});

db.models.OrderMeta = OrderMeta.init({
  	meta_id: {
    	type: Sequelize.BIGINT( 20 ),
    	primaryKey: true
  	},
  	post_id: {
    	type: Sequelize.BIGINT( 20 ),
  	},
  	meta_key: {
    	type: Sequelize.STRING,
    	allowNull: false,
  	},
  	meta_value: {
    	type: Sequelize.STRING
  	}
}, {
  	sequelize: db.sequelize,
  	modelName: 'wp_postmeta'
});

db.models.OrderLineItem = OrderLineItem.init({
  	order_item_id: {
    	type: Sequelize.BIGINT( 20 ),
    	primaryKey: true
  	},
  	order_item_name: {
    	type: Sequelize.TEXT( 'tiny' ),
    	allowNull: false
  	},
  	order_item_type: {
    	type: Sequelize.STRING( 200 ),
    	allowNull: false,
    	defaultValue: 'line_item'
  	},
  	order_id: {
    	type: Sequelize.BIGINT( 20 ),
    	allowNull: false,
  	}
}, {
  	sequelize: db.sequelize,
  	modelName: 'wp_woocommerce_order_items'
});

db.models.OrderLineItemMeta = OrderLineItemMeta.init({
  	meta_id: {
    	type: Sequelize.BIGINT( 20 ),
    	primaryKey: true
  	},
  	order_item_id: {
    	type: Sequelize.BIGINT( 20 ),
  	},
  	meta_key: {
    	type: Sequelize.STRING,
    	allowNull: false,
  	},
  	meta_value: {
    	type: Sequelize.TEXT
  	}
}, {
  	sequelize: db.sequelize,
  	modelName: 'wp_woocommerce_order_itemmeta'
});

db.models.Product.hasOne(db.models.ProductPrice, { foreignKey: 'post_id', onDelete: 'CASCADE' });
db.models.ProductPrice.belongsTo(db.models.Product, { as: 'product', foreignKey: 'post_id' });
db.models.Order.hasMany(db.models.OrderMeta, { foreignKey: 'post_id', onDelete: 'CASCADE' });
db.models.OrderMeta.belongsTo(db.models.Order, { as: 'order', foreignKey: 'post_id' });
db.models.Order.hasMany(db.models.OrderLineItem, { foreignKey: 'order_id', onDelete: 'CASCADE' });
db.models.OrderLineItem.belongsTo(db.models.Order, { as: 'order', foreignKey: 'order_id' });
db.models.OrderLineItem.hasMany(db.models.OrderLineItemMeta, { foreignKey: 'order_item_id', onDelete: 'CASCADE' });
db.models.OrderLineItemMeta.belongsTo(db.models.OrderLineItem, {  as: 'order_item', foreignKey: 'order_item_id'  });

db.loadFixtures = async function( callback ) {
    await sequelize_fixtures.loadFile( './tests/e2e-tests/fixtures/test_data.json', db.models ).then( async ( { models } ) => {
		await callback( models );
    } );

	await db.models.OrderMeta.destroy({
    	where: {
      		meta_key: 'wc_connect_labels'
    	}
  	});

	await db.sequelize.close();
};

export default db;
